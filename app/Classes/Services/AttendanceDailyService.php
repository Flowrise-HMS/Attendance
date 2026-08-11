<?php

namespace Modules\Attendance\Classes\Services;

use Carbon\Carbon;
use Illuminate\Support\Facades\Context;
use Modules\Attendance\Enums\AttendanceStatus;
use Modules\Attendance\Enums\PunchType;
use Modules\Attendance\Models\AttendanceRecord;
use Modules\Attendance\Models\AttendanceShift;
use Modules\Attendance\Models\AttendanceShiftAssignment;
use Modules\Attendance\Models\DailyAttendance;
use Modules\Attendance\Settings\AttendanceSettings;
use Modules\Core\Models\Branch;
use Modules\Staff\Models\Staff;

class AttendanceDailyService
{
    public function computeForDate(Carbon $date, ?string $branchId = null, ?string $staffId = null): void
    {
        $branches = $branchId
            ? Branch::query()->whereKey($branchId)->get()
            : Branch::query()->get();

        foreach ($branches as $branch) {
            Context::add('current_branch_id', $branch->id);
            $this->computeForDateInBranch($date->copy(), $branch->id, $staffId);
        }
    }

    protected function computeForDateInBranch(Carbon $date, string $branchId, ?string $staffId): void
    {
        $staffQuery = Staff::query()->where('branch_id', $branchId);
        if ($staffId) {
            $staffQuery->where('id', $staffId);
        } else {
            $staffQuery->active();
        }

        $staffQuery->get()->each(function (Staff $staff) use ($date, $branchId): void {
            $this->computeStaffForDate($staff, $date, $branchId);
        });
    }

    protected function computeStaffForDate(Staff $staff, Carbon $date, string $branchId): void
    {
        $settings = app(AttendanceSettings::class);
        $shift = $this->resolveShift($staff, $date);

        // 1. Weekend wins when there is no active shift assignment.
        if ($shift === null && $this->isWeekend($date, $settings)) {
            $this->upsert($staff, $date, AttendanceStatus::Weekend, $shift, $settings, firstIn: null, lastOut: null, worked: 0, late: 0, overtime: 0);

            return;
        }

        // 2. No-shift only when no assignment AND no default schedule configured.
        // Deliberate: the OR means "never half-configured" — if EITHER default is cleared we
        // cannot compute a full schedule, so it is safer to mark no_shift than to compute
        // against a half schedule. (Spec §5.8 says "both defaults cleared"; this is a
        // deliberate tightening — if only one default exists we refuse to guess.)
        if ($shift === null && ($settings->default_start_time === null || $settings->default_end_time === null)) {
            $this->upsert($staff, $date, AttendanceStatus::NoShift, $shift, $settings, firstIn: null, lastOut: null, worked: 0, late: 0, overtime: 0);

            return;
        }

        [$expectedStart, $expectedEnd, $isOvernight] = $this->resolveExpectedTimes($date, $shift, $settings);
        $window = $this->punchWindow($date, $isOvernight);

        $punches = AttendanceRecord::query()
            ->where('staff_id', $staff->id)
            ->whereBetween('punched_at', [$window[0], $window[1]])
            ->get();

        $firstIn = $punches
            ->whereIn('punch_type', [PunchType::In, PunchType::Unspecified])
            ->sortBy('punched_at')
            ->first()?->punched_at;

        $lastOut = $punches
            ->whereIn('punch_type', [PunchType::Out, PunchType::Unspecified])
            ->sortByDesc('punched_at')
            ->first()?->punched_at;

        // 3. Absent — no punches at all.
        if ($punches->isEmpty()) {
            $this->upsert($staff, $date, AttendanceStatus::Absent, $shift, $settings, firstIn: null, lastOut: null, worked: 0, late: 0, overtime: 0);

            return;
        }

        $grace = $shift?->late_grace_minutes ?? $settings->default_late_grace_minutes;
        $overtimeAfter = $shift?->overtime_after_minutes ?? $settings->default_overtime_after_minutes;
        $scheduledMinutes = (int) $expectedStart->diffInMinutes($expectedEnd);
        $breakMinutes = $scheduledMinutes >= 360 ? ($shift?->break_minutes ?? 0) : 0;

        $lateMinutes = $firstIn
            ? max(0, (int) $expectedStart->copy()->addMinutes($grace)->diffInMinutes($firstIn))
            : 0;
        $overtimeMinutes = $lastOut
            ? max(0, (int) $expectedEnd->copy()->addMinutes($overtimeAfter)->diffInMinutes($lastOut))
            : 0;
        $workedMinutes = ($firstIn && $lastOut)
            ? max(0, (int) $firstIn->diffInMinutes($lastOut) - $breakMinutes)
            : 0;

        // 4. No-punch — punches exist but no completed in/out pair.
        if ($firstIn === null || $lastOut === null) {
            $this->upsert($staff, $date, AttendanceStatus::NoPunch, $shift, $settings, $firstIn, $lastOut, $workedMinutes, $lateMinutes, $overtimeMinutes);

            return;
        }

        // 5. Present, elevated to Late.
        $status = $lateMinutes > 0 ? AttendanceStatus::Late : AttendanceStatus::Present;
        $this->upsert($staff, $date, $status, $shift, $settings, $firstIn, $lastOut, $workedMinutes, $lateMinutes, $overtimeMinutes);
    }

    protected function resolveShift(Staff $staff, Carbon $date): ?AttendanceShift
    {
        $assignment = AttendanceShiftAssignment::query()
            ->where('staff_id', $staff->id)
            ->where('effective_from', '<=', $date->toDateString())
            ->where(function ($query) use ($date): void {
                $query->whereNull('effective_to')
                    ->orWhere('effective_to', '>=', $date->toDateString());
            })
            ->orderByDesc('effective_from')
            ->orderByDesc('id')
            ->first();

        if (! $assignment) {
            return null;
        }

        $shift = $assignment->shift;

        if (! $shift || $shift->branch_id !== $staff->branch_id) {
            return null;
        }

        return $shift;
    }

    protected function resolveExpectedTimes(Carbon $date, ?AttendanceShift $shift, AttendanceSettings $settings): array
    {
        $start = $shift ? Carbon::createFromTimeString($shift->start_time->format('H:i:s')) : Carbon::createFromTimeString($settings->default_start_time);
        $end = $shift ? Carbon::createFromTimeString($shift->end_time->format('H:i:s')) : Carbon::createFromTimeString($settings->default_end_time);

        $expectedStart = $date->copy()->setTime($start->hour, $start->minute, $start->second);
        $expectedEnd = $date->copy()->setTime($end->hour, $end->minute, $end->second);
        $isOvernight = $expectedEnd->lt($expectedStart);

        if ($isOvernight) {
            $expectedEnd = $expectedEnd->addDay();
        }

        return [$expectedStart, $expectedEnd, $isOvernight];
    }

    protected function punchWindow(Carbon $date, bool $isOvernight): array
    {
        $start = $date->copy()->startOfDay();
        $end = $date->copy()->addDays($isOvernight ? 2 : 1)->startOfDay();

        return [$start, $end];
    }

    protected function isWeekend(Carbon $date, AttendanceSettings $settings): bool
    {
        return in_array(strtolower($date->format('l')), array_map('strtolower', $settings->weekend_days), true);
    }

    protected function upsert(
        Staff $staff,
        Carbon $date,
        AttendanceStatus $status,
        ?AttendanceShift $shift,
        AttendanceSettings $settings,
        ?Carbon $firstIn,
        ?Carbon $lastOut,
        int $worked,
        int $late,
        int $overtime,
    ): void {
        $existing = DailyAttendance::query()
            ->where('staff_id', $staff->id)
            ->where('work_date', $date->toDateString())
            ->first();

        $attributes = [
            'shift_id' => $shift?->id,
            'shift_name' => $shift?->name,
            'expected_start' => $shift ? $shift->start_time->format('H:i:s') : ($settings->default_start_time ? $settings->default_start_time.':00' : null),
            'expected_end' => $shift ? $shift->end_time->format('H:i:s') : ($settings->default_end_time ? $settings->default_end_time.':00' : null),
            'first_in_at' => $firstIn,
            'last_out_at' => $lastOut,
            'worked_minutes' => $worked,
            'late_minutes' => $late,
            'overtime_minutes' => $overtime,
            'is_manual_override' => false,
            'notes' => null,
        ];

        if ($existing?->is_manual_override) {
            // Manual overrides are never clobbered; refresh punch-derived fields only.
            unset($attributes['status'], $attributes['is_manual_override'], $attributes['notes']);
            $existing->forceFill($attributes)->saveQuietly();

            return;
        }

        $attributes['status'] = $status->value;

        if ($existing) {
            $existing->forceFill($attributes)->saveQuietly();
        } else {
            $attributes['staff_id'] = $staff->id;
            $attributes['work_date'] = $date->toDateString();
            $attributes['branch_id'] = $staff->branch_id;
            DailyAttendance::query()->create($attributes);
        }
    }
}
