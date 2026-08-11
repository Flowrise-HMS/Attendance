<?php

use Modules\Attendance\Enums\AttendanceStatus;
use Modules\Attendance\Enums\PunchType;
use Modules\Attendance\Models\AttendanceRecord;
use Modules\Attendance\Models\DailyAttendance;
use Modules\Attendance\Settings\AttendanceSettings;
use Modules\Core\Models\Branch;
use Modules\Staff\Models\Staff;
use Tests\TestCase;

uses(TestCase::class);

beforeEach(function (): void {
    $this->migrateModules(['Core', 'Staff', 'Attendance']);

    $this->branchA = Branch::factory()->create();
    $this->branchB = Branch::factory()->create();

    $this->staffA = Staff::factory()->create(['branch_id' => $this->branchA->id, 'zk_user_id' => 'ZKT-A']);
    $this->staffB = Staff::factory()->create(['branch_id' => $this->branchB->id, 'zk_user_id' => 'ZKT-B']);

    AttendanceSettings::fake([
        'default_start_time' => '08:00',
        'default_end_time' => '17:00',
        'weekend_days' => ['saturday', 'sunday'],
    ]);
});

it('computes daily attendance for every branch when no branch is given', function (): void {
    AttendanceRecord::factory()->create([
        'branch_id' => $this->branchA->id,
        'staff_id' => $this->staffA->id,
        'badge_number' => 'ZKT-A',
        'punched_at' => '2026-08-10 08:00:00',
        'punch_type' => PunchType::In,
    ]);
    AttendanceRecord::factory()->create([
        'branch_id' => $this->branchA->id,
        'staff_id' => $this->staffA->id,
        'badge_number' => 'ZKT-A',
        'punched_at' => '2026-08-10 17:00:00',
        'punch_type' => PunchType::Out,
    ]);

    AttendanceRecord::factory()->create([
        'branch_id' => $this->branchB->id,
        'staff_id' => $this->staffB->id,
        'badge_number' => 'ZKT-B',
        'punched_at' => '2026-08-10 08:00:00',
        'punch_type' => PunchType::In,
    ]);

    $this->artisan('attendance:compute-daily', ['--date' => '2026-08-10'])
        ->assertSuccessful();

    $this->assertDatabaseHas('daily_attendance', [
        'staff_id' => $this->staffA->id,
        'status' => AttendanceStatus::Present->value,
    ]);
    $this->assertDatabaseHas('daily_attendance', [
        'staff_id' => $this->staffB->id,
        'status' => AttendanceStatus::NoPunch->value,
    ]);
});

it('restricts computation to a single branch with --branch', function (): void {
    AttendanceRecord::factory()->create([
        'branch_id' => $this->branchA->id,
        'staff_id' => $this->staffA->id,
        'badge_number' => 'ZKT-A',
        'punched_at' => '2026-08-10 08:00:00',
        'punch_type' => PunchType::In,
    ]);
    AttendanceRecord::factory()->create([
        'branch_id' => $this->branchA->id,
        'staff_id' => $this->staffA->id,
        'badge_number' => 'ZKT-A',
        'punched_at' => '2026-08-10 17:00:00',
        'punch_type' => PunchType::Out,
    ]);

    $this->artisan('attendance:compute-daily', ['--date' => '2026-08-10', '--branch' => $this->branchA->id])
        ->assertSuccessful();

    $this->assertDatabaseHas('daily_attendance', [
        'staff_id' => $this->staffA->id,
        'status' => AttendanceStatus::Present->value,
    ]);
    $this->assertDatabaseMissing('daily_attendance', ['staff_id' => $this->staffB->id]);
});

it('restricts computation to one staff member with --staff', function (): void {
    AttendanceRecord::factory()->create([
        'branch_id' => $this->branchA->id,
        'staff_id' => $this->staffA->id,
        'badge_number' => 'ZKT-A',
        'punched_at' => '2026-08-10 08:00:00',
        'punch_type' => PunchType::In,
    ]);
    AttendanceRecord::factory()->create([
        'branch_id' => $this->branchA->id,
        'staff_id' => $this->staffA->id,
        'badge_number' => 'ZKT-A',
        'punched_at' => '2026-08-10 17:00:00',
        'punch_type' => PunchType::Out,
    ]);

    $this->artisan('attendance:compute-daily', ['--date' => '2026-08-10', '--staff' => $this->staffA->id])
        ->assertSuccessful();

    $this->assertDatabaseHas('daily_attendance', [
        'staff_id' => $this->staffA->id,
        'status' => AttendanceStatus::Present->value,
    ]);
    $this->assertDatabaseMissing('daily_attendance', ['staff_id' => $this->staffB->id]);
});

it('defaults to yesterday when no --date is given', function (): void {
    $this->travelTo('2026-08-11 01:00:00');

    $this->artisan('attendance:compute-daily')->assertSuccessful();

    $date = '2026-08-10';
    $this->assertDatabaseHas('daily_attendance', [
        'staff_id' => $this->staffA->id,
        'work_date' => $date,
        'status' => AttendanceStatus::Absent->value,
    ]);
});

it('does not pre-create today rows when run at the 01:00 scheduled time', function (): void {
    $this->travelTo('2026-08-11 01:00:00');

    $this->artisan('attendance:compute-daily')->assertSuccessful();

    expect(DailyAttendance::query()->where('staff_id', $this->staffA->id)->where('work_date', '2026-08-11')->exists())->toBeFalse();
});

it('reconcile-unmapped reports punches without a linked staff member', function (): void {
    AttendanceRecord::factory()->create([
        'branch_id' => $this->branchA->id,
        'staff_id' => null,
        'badge_number' => 'GHOST-1',
        'punched_at' => now()->subDay(),
        'punch_type' => PunchType::In,
    ]);
    AttendanceRecord::factory()->create([
        'branch_id' => $this->branchA->id,
        'staff_id' => null,
        'badge_number' => 'GHOST-1',
        'punched_at' => now()->subDays(2),
        'punch_type' => PunchType::Out,
    ]);

    $this->artisan('attendance:reconcile-unmapped')
        ->expectsOutputToContain('GHOST-1')
        ->assertSuccessful();
});

it('reconcile-unmapped reports no unmapped punches cleanly', function (): void {
    $this->artisan('attendance:reconcile-unmapped')
        ->expectsOutputToContain('No unmapped punches found.')
        ->assertSuccessful();
});

it('reconcile-unmapped ignores old punches outside the --days window', function (): void {
    AttendanceRecord::factory()->create([
        'branch_id' => $this->branchA->id,
        'staff_id' => null,
        'badge_number' => 'GHOST-1',
        'punched_at' => now()->subDays(60),
        'punch_type' => PunchType::In,
    ]);

    $this->artisan('attendance:reconcile-unmapped', ['--days' => 30])
        ->expectsOutputToContain('No unmapped punches found.')
        ->assertSuccessful();
});

it('registers the attendance commands in artisan', function (): void {
    $this->artisan('list')
        ->expectsOutputToContain('attendance:pull')
        ->expectsOutputToContain('attendance:compute-daily')
        ->expectsOutputToContain('attendance:reconcile-unmapped')
        ->assertSuccessful();
});
