# Attendance Module

> Collect staff time-and-attendance from ZKTeco biometric machines (PUSH, PULL, and pen-drive CSV import), track shifts and staff assignments, and compute daily late/overtime summaries for FlowRise HMS.

The `Attendance` module depends on `Modules\Core` and `Modules\Staff`. All three ingestion paths write into the same `attendance_records` table through one shared service with a single dedup key, and all derived data flows through one computation service.

This document is the canonical guide for the module. It is meant to be useful to:

- HR and admin staff who need to understand how punches become daily attendance
- administrators who need to configure machines, shifts, and settings
- developers who need to extend or maintain the module safely

## Current Status

Attendance is **complete** for operational use.

What is available:

- real-time **PUSH** ingestion from ZKTeco devices over `/iclock/*` (in-house, no dependency)
- **PULL** TCP sync via `attendance:pull` (dependency: `jmrashed/zkteco`)
- **CSV import** of pen-drive exports (ZKTime and Biostar layouts) through the Filament UI
- shifts, staff shift assignments (with overlap rejection and deterministic resolution)
- daily computation (present/late/absent/weekend/no_shift/no_punch), late/overtime/worked minutes
- a Filament dashboard with stat cards and charts, plus five resources and a settings page, all inside the **Attendance** cluster in the **Operations** sidebar group
- staff-record tabs (Attendance records, Daily attendance) on the Staff module's resource
- an 81-test Pest suite (11 files) covering ingestion, computation, commands, models, exporter and authorization

Verified against code on 2026-09-20.

Explicitly out of scope (design decision, not deferred work):

- payroll calculation, paid hours, late deductions, overtime pay rates
- automated rotating rosters, shift swaps, leave/holiday calendar integration
- approvals workflow (admin edits are direct)
- web-based employee self clock-in/out
- real-time device command queueing (device time-sync can be added later)

Use this README as both:

- the mental model for how Attendance is supposed to work
- the implementation map for what exists right now

## Mental Model

### The shortest explanation

Attendance turns **punches** (raw clock events from a machine, import, or admin) into **daily attendance** (one derived summary per staff member per work date, with a status and late/overtime minutes).

- **Machines** collect punches (PUSH over HTTP, PULL over TCP, or pen-drive exports)
- **Shifts + Shift Assignments** decide the expected window for each staff member on each date
- **`attendance_records`** stores every punch exactly once (dedup by SHA-256 hash)
- **`daily_attendance`** materializes the computed result so charts and reports are fast

### The core chain

```text
ZKTeco device / CSV import
        │  PUSH / PULL / import
        ▼
AttendanceIngestionService   (normalize → map badge→staff → dedup → store)
        ▼
attendance_records           (raw punches, dedup_hash unique)
        ▼
AttendanceDailyService       (shift resolution → weekend gate → punch pairing → status)
        ▼
daily_attendance             (derived summaries, one row per staff+work_date)
        ▼
Filament: resources, dashboard widgets, settings page
```

### Why this matters

- **One shared pipeline**: PUSH, PULL, and CSV import all go through `AttendanceIngestionService` with a single source-independent dedup hash, so the same physical punch is never stored twice even when a machine has both push and pull enabled.
- **Idempotent computation**: `AttendanceDailyService` upserts `daily_attendance` and never clobbers a manual status override, so recomputation is safe to run daily and after every ingest batch.
- **Branch-safe by construction**: ingestion takes its branch from the machine record (never from an authenticated user), and non-HTTP work (commands, jobs) resolves branches explicitly, because devices and queues have no auth context.

## Data Model

All models extend `Modules\Core\Models\BaseModel` (auditing + `BelongsToBranch`) and add `HasUuids` and (where noted) `SoftDeletes`.

**Soft-delete policy (important):** `attendance_machines`, `attendance_shifts`, and `attendance_shift_assignments` use `SoftDeletes`. `attendance_records` and `daily_attendance` **hard-delete** by design: both carry unique indexes (`dedup_hash` and `(staff_id, work_date)`), and a soft-deleted row would keep occupying its unique key, blocking re-ingest or recompute.

**Time convention:** `punched_at` is **always stored in the app timezone** (`config('app.timezone')`). Ingest parses raw device timestamps in the machine's `timezone` and converts to the app timezone before storing, so late/overtime/shift math never needs per-machine conversion.

### Tables

| Table | Purpose | Soft deletes |
|-------|---------|--------------|
| `attendance_machines` | ZKTeco devices (serial unique, push/pull toggles, timezone, IP/port for PULL) | yes |
| `attendance_shifts` | Shift definitions (`start_time`, `end_time`, grace/overtime thresholds, `break_minutes`) | yes |
| `attendance_shift_assignments` | Staff ↔ shift with `effective_from`/`effective_to` (overlaps rejected) | yes |
| `attendance_records` | Raw punches (dedup_hash unique, staff_id nullable for unmapped badges) | no |
| `daily_attendance` | Derived summaries, unique `(staff_id, work_date)` | no |

Plus: `zk_user_id` (nullable string) added to `staff` for badge resolution, and `AttendanceSettings` (Spatie, group `attendance`).

### Badge resolution

`zk_user_id` → `staff_number` → unmapped (`staff_id = null`, surfaced for linking). The `staff_number` fallback is exact-string equality only and best-effort; the reliable path is `zk_user_id`, set via the "Link staff" action on unmapped punches or on the Staff form.

### Enums

- `PunchSource`: `push`, `pull`, `import`, `manual`
- `PunchType`: `in`, `out`, `break_in`, `break_out`, `overtime_in`, `overtime_out`, `unspecified`
- `VerifyType`: `password`, `fingerprint`, `face`, `card`, `other`
- `AttendanceStatus`: `present`, `late`, `absent`, `on_leave`, `weekend`, `holiday`, `no_shift`, `no_punch` (`on_leave`/`holiday` only via manual override — no leave/holiday calendar yet)

### Deduplication

```text
dedup_hash = sha256(machine_key + '|' + badge_number + '|' + punched_at_app)
machine_key = machine_id (uuid) — or the literal 'unassigned' when machine_id is NULL
```

`source` and `status_code` are intentionally **not** part of the hash, so the same physical punch is deduplicated across PUSH/PULL/import, while different machines still produce different records.

## Ingestion Paths

### 1. PUSH (device → server, in-house)

Routes under `/iclock/` mounted **without** the `web` middleware group (devices are stateless):

| Method | Path | Purpose |
|--------|------|---------|
| GET | `/iclock/cdata` | handshake / config negotiation — respond `OK` |
| POST | `/iclock/cdata` | data upload; `table=ATTLOG` parsed; respond `OK` |
| GET | `/iclock/getrequest` | command poll — respond `OK` (no command queue in v1) |

- Device identified by `?SN=serial`; validated against a machine with `push_enabled` and `is_active`.
- Unknown serials are dropped and answered with a device-friendly `OK` (never a hard error that triggers retry storms).
- ATTLOG lines are tab-separated: `PIN<tab>DateTime<tab>Status<tab>VerifyType<tab>WorkCode...`.
- Non-ATTLOG tables (`OPERLOG`, `USERINFO`, `BIODATA`) are acknowledged and ignored in v1.
- Any request from a known machine updates `last_seen_at`; body size is limited to 10 MB.

> **Security note:** PUSH auth is serial-only for v1. The `/iclock/*` routes must **not** be publicly reachable — restrict them via internal VLAN, reverse-proxy IP allowlist, or at minimum no port exposure. A per-machine shared secret / IP allowlist is a documented optional hardening.

### 2. PULL (server → device, `jmrashed/zkteco`)

`attendance:pull {--machine=}` — for each active machine with `pull_enabled` (or the given machine UUID):

1. connect to `ip_address:port` (default port 4370)
2. `getAttendance()` fetches the **full device log** every run (it does not filter by `last_sync_at`)
3. feed into `AttendanceIngestionService` with `PunchSource::Pull`
4. on success: update `last_sync_at`, mark online; on failure: leave `last_sync_at` untouched for retry, mark offline
5. always `disconnect()` in a `finally`

The source-independent dedup hash is what keeps full-log re-fetches and PUSH+PULL overlap from duplicating.

### 3. CSV import (pen-drive export, Filament)

`AttendanceRecordImporter` (Filament built-in import) on the Attendance Records list page:

- import columns: `badge_number`, `punched_at`, `verify_type`, `status_code`; `machine_id` is **not** a column — the admin picks the machine in the ImportAction options ("unassigned" allowed)
- the `Status` column stores the raw value in `status_code` and `punch_type` is derived from that same Status value (ZKTime `0`=in, `1`=out; Biostar via the same rules), so first-in/last-out pairing stays correct for imported rows
- the file is normalized to UTF-8 before parsing (pen-drive exports are often UTF-16/GB2312)
- a row whose `dedup_hash` already exists is skipped entirely (the original record stays untouched)
- branch enforcement: the selected machine must belong to the admin's current branch; "unassigned" imports use the admin's current branch
- re-importing the same file must select the **same machine** as the original import, otherwise rows duplicate

## Daily Computation — `AttendanceDailyService`

`computeForDate(Carbon $date, ?string $branchId = null, ?string $staffId = null): void`

For each active staff member in the target branch:

1. **Shift resolution**: active assignment for the date (highest `effective_from`, then newest `id`). Cross-branch shift → treated as no assignment. No assignment → the `AttendanceSettings` default schedule applies; `no_shift` only when the defaults are cleared (`null`).
2. **Weekend gate**: if the date is in `weekend_days` **and** there is no active shift assignment for that date → status `weekend`, all minutes 0, stop. A weekend date **with** an assignment computes normally (24/7 scheduling).
3. **Punch pairing**: `first_in_at` = earliest `in`/`unspecified`; `last_out_at` = latest `out`/`unspecified`. Break punches are ignored in v1 (breaks are modelled by the shift's flat `break_minutes`).
4. **Overnight shifts**: `work_date` is the shift's start date; the expected end is on the following calendar day.
5. **Late**: `max(0, first_in − (expected_start + grace))` — recorded for reference even on `no_punch` days, but late is only *elevated* from `present`.
6. **Overtime**: `max(0, last_out − (expected_end + overtime_after))`.
7. **Worked**: `last_out − first_in − break_minutes` (break deducted only when the scheduled shift is ≥ 6 hours).
8. **Status precedence** (first match wins; a manual override always wins and is preserved):
   `weekend` → `no_shift` → `absent` (no punches) → `no_punch` (punches but no complete in/out pair) → `present` (elevated to `late` when late minutes > 0).
9. **Upsert** `daily_attendance` with snapshot fields (`shift_name`, `expected_start`, `expected_end`).

Idempotent — safe to run daily and on every ingest batch.

### Scheduling

- `attendance:pull` runs **every 15 minutes** (fixed v1 cadence). `pull_interval_minutes` from settings does not yet control the cadence dynamically — it only gates whether the job runs at all (a value of `0` disables it).
- `attendance:compute-daily` runs daily at **01:00** local and computes **yesterday** (plus overnight windows that started yesterday). Today is **not** batch-finalised at 01:00 — pre-creating a full roster of `absent`/`no_punch` rows before anyone clocks in would mislead HR. Today is driven by **ingest-triggered recompute**: as punches arrive, only the affected `(staff_id, work_date)` pairs are recomputed (with the prior calendar date also included for overnight shifts).

Schedules are defined module-side via `configureSchedules()`; no changes to `bootstrap/app.php`.

## Filament UI

All resources and pages are registered under the `AttendanceCluster` (slug `attendance-cluster`, sidebar group **Operations**, label **Attendance**, URL `/attendance-cluster`) via the `$cluster` property — files remain in `app/Filament/{Resources,Pages}` but are grouped in the Filament sidebar under **Operations → Attendance**. A dedicated Filament `AttendanceCluster` class lives in `app/Filament/Clusters/Attendance/`.

### Resources

| Resource | Features |
|----------|----------|
| **Attendance Machines** | list (Name, Serial number, IP Address, Status online/offline badge, Last seen, PUSH, PULL); form: name, serial, IP, port, comm key, model, timezone, Accept device push / Poll device toggles, Is active; "Sync now" and "Test connection" row actions |
| **Shifts** (menu label **Attendance Shifts**) | CRUD; columns Name, Start time, End time, Type, Break minutes, Is active; overnight indicator (`end < start`); grace + overtime fields; color |
| **Shift Assignments** (menu label **Attendance Shift Assignments**) | list by staff or shift (Staff, Shift, Effective from, Effective to, Note); shift dropdown filtered to the staff's branch; overlap validation |
| **Attendance Records** | raw punches: staff, badge, punched_at, machine, source, punch type, verify type; filters (date range, machine, staff, source, punch type, **unmapped**); edit/delete; **Import** toolbar action; "Link staff" action on unmapped rows |
| **Daily Attendance** (menu label **Daily Attendances**) | status badges with color, Worked / Late / OT columns, First in at / Last out at, Override; filters (Work from / Work until, Status, Staff); **Export daily attendances** CSV; per-row status override action |

### Pages & Widgets

- **Attendance Dashboard** (first item in the cluster, slug `attendance-dashboard`): stat cards (present/late/absent/on shift today), punches-per-hour bar (today), 14-day attendance trend (area), late-vs-overtime distribution, recent clock-ins/outs table (live-polled).
- **ManageAttendanceSettings** (navigation label **Attendance** in the cluster's Settings group, Spatie `SettingsPage`).
- Within the cluster the resources sit in the in-cluster `ADMINISTRATION` group and the settings page in `SETTINGS`.

### Settings (`AttendanceSettings`)

| Property | Default |
|----------|---------|
| `default_late_grace_minutes` | 15 |
| `default_overtime_after_minutes` | 60 |
| `default_start_time` | `08:00` (nullable) |
| `default_end_time` | `17:00` (nullable) |
| `weekend_days` | `[saturday, sunday]` |
| `pull_interval_minutes` | 15 (gates the 15-min pull schedule on/off in v1) |
| `push_enabled` | true |

**Default schedule vs `no_shift`:** when a staff member has no active shift assignment, the default schedule applies whenever both `default_start_time`/`default_end_time` are set. `no_shift` only occurs when the admin explicitly clears both defaults — an org that fully relies on shift assignments.

### Permissions & Roles

Access is enforced two ways:

1. **Shield model permissions** — the standard `view_any`/`view`/`create`/`update`/`delete` set generated for each resource's model (e.g. `View AttendanceRecord`, `Update AttendanceRecord`). The policy classes (`AttendanceMachinePolicy`, `AttendanceShiftPolicy`, `AttendanceShiftAssignmentPolicy`, `AttendanceRecordPolicy`, `DailyAttendancePolicy`) check these abilities via `auth()->user()->can(...)` and additionally enforce branch checks — a user may only act on records belonging to their current branch.

2. **Custom action permissions** — shipped in `Modules/Attendance/config/config.php` under `permissions` and merged into `filament-shield.custom_permissions` by `Modules\Core`, exposed under an "Attendance" section in Shield:

   | Permission | Gates |
   |------------|-------|
   | `import_attendance_records` | CSV import action on Attendance Records |
   | `override_daily_attendance_status` | Daily Attendance status-override action |
   | `export_daily_attendance` | Daily Attendance CSV export |

   `AttendanceCustomPermissionSeeder` grants the three to `super_admin` (and to an `admin` role if one exists).

3. **Page permissions** — the Attendance Dashboard and ManageAttendanceSettings pages use Filament Shield's `HasPageShield`, so access is gated by the generated `View AttendanceDashboard` / `View ManageAttendanceSettings` permissions (there are no `view_attendance_dashboard` / `manage_attendance_settings` custom permissions).

**Staff "view own":** when a user cannot `ViewAny AttendanceRecord`, the Attendance Records query is scoped to their own staff profile (resolved via the existing `Staff.user_id` ↔ `users.id` mapping), and `AttendanceRecordPolicy::view()` allows viewing their own records. This is how the "staff: view own" access works — no extra permission is needed.

## Administrator Guide

### Recommended setup order

```text
1. Run Core migrations and seeders (branches, roles, staff)
2. Run Attendance migrations
3. Run Attendance seeders (settings + custom permissions)
4. Register machines (serial, timezone, push/pull toggles)
5. Define shifts (grace/overtime thresholds, break minutes)
6. Assign staff to shifts with effective dates
7. Link badges: set zk_user_id on staff (or "Link staff" on unmapped punches)
8. Assign roles/permissions in Shield
9. Verify: push a fixture to /iclock/cdata, or run attendance:pull
```

### Useful commands

```bash
php artisan module:migrate Attendance
php artisan module:seed Attendance
php artisan attendance:pull                 # PULL all pull-enabled machines
php artisan attendance:pull --machine=<uuid> # PULL one machine
php artisan attendance:compute-daily        # recompute yesterday
php artisan attendance:compute-daily --date=2026-08-10
php artisan attendance:compute-daily --date=2026-08-10 --branch=<uuid> --staff=<uuid>
php artisan attendance:reconcile-unmapped   # list punches with no linked staff (--days=30)
php artisan test --compact Modules/Attendance/tests
```

### Operational notes

- **Timezone changes are destructive to dedup hashes**: `dedup_hash` embeds `punched_at` in the app timezone. If `config('app.timezone')` is ever changed in production, existing hashes no longer match — recompute `dedup_hash` for existing rows in a one-time migration at the same time.
- **Re-importing a pen-drive file** must select the same machine as the original import, or rows duplicate (different machine key and parse timezone).
- **Device health** is derived, not stored: online = successful connect/push within the last `pull_interval` window.
- A machine may have both `push_enabled` and `pull_enabled`; dedup guarantees no duplicates.

## Developer Guide

### Module boundaries

Attendance depends on `Modules\Core` (BaseModel, Branch, Branch-context) and `Modules\Staff` (staff records, `staff_number`). It adds exactly one field to Staff: nullable `zk_user_id`. It does **not** touch payroll, scheduling/rosters, or leave.

### Key classes

| Class | Role |
|-------|------|
| `Classes\Services\AttendanceIngestionService` | Shared pipeline: normalize → map badge → dedup → store → dispatch recompute |
| `Classes\Services\AttendanceDailyService` | Daily computation (shift resolution, weekend gate, pairing, status precedence) |
| `Classes\Services\AttendancePullService` | ZKTeco TCP client wrapper (`jmrashed/zkteco`) |
| `Http\Controllers\IclockController` | `/iclock/*` PUSH endpoints |
| `Jobs\RecomputeDailyAttendanceJob` | Ingest-triggered recompute (fan-out batched per `(staff_id, work_date)`) |
| `Jobs\PullAttendanceJob` | Per-machine pull job ("Sync now" + scheduled) |
| `Settings\AttendanceSettings` | Spatie settings, group `attendance` |
| `Filament\AttendancePlugin` | Module plugin (auto-registered via `filament-modules`) |

### Providers

- `Providers\AttendanceServiceProvider` — base-class module provider; registers commands explicitly and schedules module-side via `configureSchedules()`.
- `Providers\RouteServiceProvider` — mounts `/iclock/*` without the `web` middleware.
- `Providers\EventServiceProvider` — module events/listeners.

### Branches in non-request contexts

`attendance:compute-daily`, `RecomputeDailyAttendanceJob`, and `attendance:pull` run without an authenticated user, so the `BelongsToBranch` global scope cannot resolve a branch from auth. Each command/job iterates **explicitly**: resolve the branch (from `--branch=`/job payload, or by enumerating all `Branch` records) and run with `currentBranchId` set for the duration of that iteration.

### Migrations

| File | Table |
|------|-------|
| `2026_08_10_000001_*` | `attendance_machines` |
| `2026_08_10_000002_*` | `attendance_shifts` |
| `2026_08_10_000003_*` | `attendance_shift_assignments` |
| `2026_08_10_000004_*` | `attendance_records` |
| `2026_08_10_000005_*` | `daily_attendance` |
| `settings/2026_08_10_000001_*` | `attendance` settings |

### Tests

The module test suite (`Modules/Attendance/tests`) includes **66 tests** covering the enums, ingestion, the iclock PUSH endpoint, the daily service computation matrix, models, commands, cross-source dedup, weekend gating, shift resolution, badge resolution, branch scoping, overnight recompute, authorization, and the settings page.

```bash
php artisan test Modules/Attendance/tests
```

### Metadata and package files

- `Modules/Attendance/module.json` — name, alias (`attendance`), priority, providers, requires `Modules\Core` + `Modules\Staff`.
- `Modules/Attendance/composer.json` — `flowrise-hms/attendance` path repo.
- `Modules/Attendance/config/config.php` — module name + Shield permission keys.

Keep these accurate whenever the module boundary or purpose changes.

## Related Documentation

For approved design context, see:

- `docs/superpowers/specs/2026-08-10-attendance-design.md`
- `docs/superpowers/plans/2026-08-10-attendance-module.md`

## Summary

If you remember only five things about this module, remember these:

1. All punches funnel through one ingestion service with a single source-independent dedup hash — PUSH/PULL/import never duplicate a physical punch.
2. `daily_attendance` is materialized and idempotent; recomputation never clobbers a manual override.
3. Today's rows are driven by ingest-triggered recompute, not by the 01:00 batch (which computes yesterday).
4. `punched_at` is always stored in the app timezone; machine-local time is converted at ingest.
5. Ingestion and batch work resolve branches explicitly — never from an authenticated user.
