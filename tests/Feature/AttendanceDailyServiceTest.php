<?php

use Carbon\Carbon;
use Modules\Attendance\Classes\Services\AttendanceDailyService;
use Modules\Attendance\Enums\AttendanceStatus;
use Modules\Attendance\Enums\PunchType;
use Modules\Attendance\Models\AttendanceRecord;
use Modules\Attendance\Models\AttendanceShift;
use Modules\Attendance\Models\AttendanceShiftAssignment;
use Modules\Attendance\Models\DailyAttendance;
use Modules\Attendance\Settings\AttendanceSettings;
use Modules\Core\Models\Branch;
use Modules\Staff\Models\Staff;
use Tests\TestCase;

uses(TestCase::class);

beforeEach(function (): void {
    $this->migrateModules(['Core', 'Staff', 'Attendance']);

    $this->branch = Branch::factory()->create();
    $this->staff = Staff::factory()->create([
        'branch_id' => $this->branch->id,
        'zk_user_id' => 'ZKT-001',
    ]);
    $this->service = app(AttendanceDailyService::class);

    AttendanceSettings::fake([
        'default_start_time' => '08:00',
        'default_end_time' => '17:00',
        'default_late_grace_minutes' => 15,
        'default_overtime_after_minutes' => 60,
        'weekend_days' => ['saturday', 'sunday'],
    ]);
});

it('marks a full day with in/out punches as present', function (): void {
    createPunch('ZKT-001', '2026-08-10 08:00:00', PunchType::In);
    createPunch('ZKT-001', '2026-08-10 17:00:00', PunchType::Out);

    $this->service->computeForDate(Carbon::parse('2026-08-10'), $this->branch->id, $this->staff->id);

    $this->assertDatabaseHas('daily_attendance', [
        'staff_id' => $this->staff->id,
        'work_date' => '2026-08-10',
        'status' => AttendanceStatus::Present->value,
        'worked_minutes' => 540,
        'late_minutes' => 0,
        'overtime_minutes' => 0,
    ]);
});

it('elevates a late arrival to late status', function (): void {
    createPunch('ZKT-001', '2026-08-10 08:30:00', PunchType::In);
    createPunch('ZKT-001', '2026-08-10 17:00:00', PunchType::Out);

    $this->service->computeForDate(Carbon::parse('2026-08-10'), $this->branch->id, $this->staff->id);

    $this->assertDatabaseHas('daily_attendance', [
        'staff_id' => $this->staff->id,
        'status' => AttendanceStatus::Late->value,
        'late_minutes' => 15,
    ]);
});

it('applies the late grace period before marking late', function (): void {
    createPunch('ZKT-001', '2026-08-10 08:12:00', PunchType::In);
    createPunch('ZKT-001', '2026-08-10 17:00:00', PunchType::Out);

    $this->service->computeForDate(Carbon::parse('2026-08-10'), $this->branch->id, $this->staff->id);

    $this->assertDatabaseHas('daily_attendance', [
        'staff_id' => $this->staff->id,
        'status' => AttendanceStatus::Present->value,
        'late_minutes' => 0,
    ]);
});

it('marks absent when there are no punches', function (): void {
    $this->service->computeForDate(Carbon::parse('2026-08-10'), $this->branch->id, $this->staff->id);

    $this->assertDatabaseHas('daily_attendance', [
        'staff_id' => $this->staff->id,
        'status' => AttendanceStatus::Absent->value,
        'worked_minutes' => 0,
    ]);
});

it('marks no_punch when only a single punch exists', function (): void {
    createPunch('ZKT-001', '2026-08-10 08:00:00', PunchType::In);

    $this->service->computeForDate(Carbon::parse('2026-08-10'), $this->branch->id, $this->staff->id);

    $this->assertDatabaseHas('daily_attendance', [
        'staff_id' => $this->staff->id,
        'status' => AttendanceStatus::NoPunch->value,
        'late_minutes' => 0,
    ]);
});

it('computes overtime beyond the threshold', function (): void {
    createPunch('ZKT-001', '2026-08-10 08:00:00', PunchType::In);
    createPunch('ZKT-001', '2026-08-10 18:30:00', PunchType::Out);

    $this->service->computeForDate(Carbon::parse('2026-08-10'), $this->branch->id, $this->staff->id);

    $this->assertDatabaseHas('daily_attendance', [
        'staff_id' => $this->staff->id,
        'status' => AttendanceStatus::Present->value,
        'overtime_minutes' => 30,
        'worked_minutes' => 630,
    ]);
});

it('marks weekend for unassigned staff on a weekend day', function (): void {
    createPunch('ZKT-001', '2026-08-15 08:00:00', PunchType::In);

    $this->service->computeForDate(Carbon::parse('2026-08-15'), $this->branch->id, $this->staff->id);

    $this->assertDatabaseHas('daily_attendance', [
        'staff_id' => $this->staff->id,
        'status' => AttendanceStatus::Weekend->value,
        'worked_minutes' => 0,
    ]);
});

it('computes normally on a weekend with an active shift assignment', function (): void {
    $shift = AttendanceShift::factory()->create([
        'branch_id' => $this->branch->id,
        'start_time' => '08:00:00',
        'end_time' => '17:00:00',
        'break_minutes' => 0,
    ]);
    AttendanceShiftAssignment::factory()->create([
        'staff_id' => $this->staff->id,
        'shift_id' => $shift->id,
        'effective_from' => '2026-08-01',
        'effective_to' => null,
    ]);

    createPunch('ZKT-001', '2026-08-15 08:00:00', PunchType::In);
    createPunch('ZKT-001', '2026-08-15 17:00:00', PunchType::Out);

    $this->service->computeForDate(Carbon::parse('2026-08-15'), $this->branch->id, $this->staff->id);

    $this->assertDatabaseHas('daily_attendance', [
        'staff_id' => $this->staff->id,
        'status' => AttendanceStatus::Present->value,
        'worked_minutes' => 540,
    ]);
});

it('marks no_shift when defaults are cleared and no assignment exists', function (): void {
    AttendanceSettings::fake([
        'default_start_time' => null,
        'default_end_time' => null,
        'weekend_days' => ['saturday', 'sunday'],
    ]);

    createPunch('ZKT-001', '2026-08-10 08:00:00', PunchType::In);

    $this->service->computeForDate(Carbon::parse('2026-08-10'), $this->branch->id, $this->staff->id);

    $this->assertDatabaseHas('daily_attendance', [
        'staff_id' => $this->staff->id,
        'status' => AttendanceStatus::NoShift->value,
        'worked_minutes' => 0,
    ]);
});

it('marks no_shift when only one default time is cleared', function (): void {
    AttendanceSettings::fake([
        'default_start_time' => null,
        'default_end_time' => '17:00',
        'weekend_days' => ['saturday', 'sunday'],
    ]);

    $this->service->computeForDate(Carbon::parse('2026-08-10'), $this->branch->id, $this->staff->id);

    $this->assertDatabaseHas('daily_attendance', [
        'staff_id' => $this->staff->id,
        'status' => AttendanceStatus::NoShift->value,
    ]);
});

it('applies shift-specific grace and overtime thresholds', function (): void {
    $shift = AttendanceShift::factory()->create([
        'branch_id' => $this->branch->id,
        'start_time' => '08:00:00',
        'end_time' => '17:00:00',
        'late_grace_minutes' => 5,
        'overtime_after_minutes' => 15,
    ]);
    AttendanceShiftAssignment::factory()->create([
        'staff_id' => $this->staff->id,
        'shift_id' => $shift->id,
        'effective_from' => '2026-08-01',
        'effective_to' => null,
    ]);

    createPunch('ZKT-001', '2026-08-10 08:10:00', PunchType::In);
    createPunch('ZKT-001', '2026-08-10 17:20:00', PunchType::Out);

    $this->service->computeForDate(Carbon::parse('2026-08-10'), $this->branch->id, $this->staff->id);

    $this->assertDatabaseHas('daily_attendance', [
        'staff_id' => $this->staff->id,
        'status' => AttendanceStatus::Late->value,
        'late_minutes' => 5,
        'overtime_minutes' => 5,
    ]);
});

it('deducts break minutes only for long shifts', function (): void {
    $shift = AttendanceShift::factory()->create([
        'branch_id' => $this->branch->id,
        'start_time' => '08:00:00',
        'end_time' => '17:00:00',
        'break_minutes' => 30,
    ]);
    AttendanceShiftAssignment::factory()->create([
        'staff_id' => $this->staff->id,
        'shift_id' => $shift->id,
        'effective_from' => '2026-08-01',
        'effective_to' => null,
    ]);

    createPunch('ZKT-001', '2026-08-10 08:00:00', PunchType::In);
    createPunch('ZKT-001', '2026-08-10 17:00:00', PunchType::Out);

    $this->service->computeForDate(Carbon::parse('2026-08-10'), $this->branch->id, $this->staff->id);

    $this->assertDatabaseHas('daily_attendance', [
        'staff_id' => $this->staff->id,
        'status' => AttendanceStatus::Present->value,
        'worked_minutes' => 510,
    ]);
});

it('computes overnight shifts across the midnight boundary', function (): void {
    $shift = AttendanceShift::factory()->overnight()->create([
        'branch_id' => $this->branch->id,
        'break_minutes' => 0,
    ]);
    AttendanceShiftAssignment::factory()->create([
        'staff_id' => $this->staff->id,
        'shift_id' => $shift->id,
        'effective_from' => '2026-08-01',
        'effective_to' => null,
    ]);

    createPunch('ZKT-001', '2026-08-10 22:00:00', PunchType::In);
    createPunch('ZKT-001', '2026-08-11 06:00:00', PunchType::Out);

    $this->service->computeForDate(Carbon::parse('2026-08-10'), $this->branch->id, $this->staff->id);

    $this->assertDatabaseHas('daily_attendance', [
        'staff_id' => $this->staff->id,
        'work_date' => '2026-08-10',
        'status' => AttendanceStatus::Present->value,
        'worked_minutes' => 480,
        'late_minutes' => 0,
    ]);
});

it('uses the highest effective_from assignment when multiple exist', function (): void {
    $shiftA = AttendanceShift::factory()->create(['branch_id' => $this->branch->id, 'name' => 'Old']);
    $shiftB = AttendanceShift::factory()->create(['branch_id' => $this->branch->id, 'name' => 'New']);

    AttendanceShiftAssignment::factory()->create([
        'staff_id' => $this->staff->id,
        'shift_id' => $shiftA->id,
        'effective_from' => '2026-07-01',
        'effective_to' => null,
    ]);
    AttendanceShiftAssignment::factory()->create([
        'staff_id' => $this->staff->id,
        'shift_id' => $shiftB->id,
        'effective_from' => '2026-08-01',
        'effective_to' => null,
    ]);

    createPunch('ZKT-001', '2026-08-10 08:00:00', PunchType::In);
    createPunch('ZKT-001', '2026-08-10 17:00:00', PunchType::Out);

    $this->service->computeForDate(Carbon::parse('2026-08-10'), $this->branch->id, $this->staff->id);

    $this->assertDatabaseHas('daily_attendance', [
        'staff_id' => $this->staff->id,
        'shift_id' => $shiftB->id,
        'shift_name' => 'New',
    ]);
});

it('treats a cross-branch shift assignment as no assignment', function (): void {
    $otherBranch = Branch::factory()->create();
    $shift = AttendanceShift::factory()->create(['branch_id' => $otherBranch->id]);
    AttendanceShiftAssignment::factory()->create([
        'staff_id' => $this->staff->id,
        'shift_id' => $shift->id,
        'effective_from' => '2026-08-01',
        'effective_to' => null,
    ]);

    $this->service->computeForDate(Carbon::parse('2026-08-10'), $this->branch->id, $this->staff->id);

    $this->assertDatabaseHas('daily_attendance', [
        'staff_id' => $this->staff->id,
        'status' => AttendanceStatus::Absent->value,
        'shift_id' => null,
    ]);
});

it('ignores punches outside the active assignment window', function (): void {
    $shift = AttendanceShift::factory()->create([
        'branch_id' => $this->branch->id,
        'start_time' => '08:00:00',
        'end_time' => '17:00:00',
    ]);
    AttendanceShiftAssignment::factory()->create([
        'staff_id' => $this->staff->id,
        'shift_id' => $shift->id,
        'effective_from' => '2026-08-01',
        'effective_to' => '2026-08-15',
    ]);

    createPunch('ZKT-001', '2026-08-20 08:00:00', PunchType::In);
    createPunch('ZKT-001', '2026-08-20 17:00:00', PunchType::Out);

    $this->service->computeForDate(Carbon::parse('2026-08-20'), $this->branch->id, $this->staff->id);

    $this->assertDatabaseHas('daily_attendance', [
        'staff_id' => $this->staff->id,
        'work_date' => '2026-08-20',
        'shift_id' => null,
    ]);
});

it('preserves a manual override on recompute', function (): void {
    DailyAttendance::factory()->create([
        'branch_id' => $this->branch->id,
        'staff_id' => $this->staff->id,
        'work_date' => '2026-08-10',
        'status' => AttendanceStatus::OnLeave,
        'is_manual_override' => true,
    ]);

    createPunch('ZKT-001', '2026-08-10 08:00:00', PunchType::In);
    createPunch('ZKT-001', '2026-08-10 17:00:00', PunchType::Out);

    $this->service->computeForDate(Carbon::parse('2026-08-10'), $this->branch->id, $this->staff->id);

    $this->assertDatabaseHas('daily_attendance', [
        'staff_id' => $this->staff->id,
        'status' => AttendanceStatus::OnLeave->value,
        'is_manual_override' => true,
        'worked_minutes' => 540,
    ]);
});

it('recreates a deleted daily row on recompute', function (): void {
    createPunch('ZKT-001', '2026-08-10 08:00:00', PunchType::In);
    createPunch('ZKT-001', '2026-08-10 17:00:00', PunchType::Out);

    $this->service->computeForDate(Carbon::parse('2026-08-10'), $this->branch->id, $this->staff->id);
    expect(DailyAttendance::query()->count())->toBe(1);

    DailyAttendance::query()->first()->delete();
    expect(DailyAttendance::query()->count())->toBe(0);

    $this->service->computeForDate(Carbon::parse('2026-08-10'), $this->branch->id, $this->staff->id);

    expect(DailyAttendance::query()->count())->toBe(1);
});

function createPunch(string $badge, string $at, PunchType $type, ?string $branchId = null): void
{
    AttendanceRecord::factory()->create([
        'branch_id' => $branchId ?? test()->branch->id,
        'staff_id' => test()->staff->id,
        'badge_number' => $badge,
        'punched_at' => $at,
        'punch_type' => $type,
    ]);
}
