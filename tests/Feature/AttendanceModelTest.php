<?php

use Modules\Attendance\Models\AttendanceMachine;
use Modules\Attendance\Models\AttendanceShift;
use Modules\Attendance\Models\AttendanceShiftAssignment;
use Modules\Core\Models\Branch;
use Modules\Staff\Models\Staff;
use Tests\TestCase;

uses(TestCase::class);

beforeEach(function (): void {
    $this->migrateModules(['Core', 'Staff', 'Attendance']);

    $this->branch = Branch::factory()->create();
    $this->staff = Staff::factory()->create(['branch_id' => $this->branch->id]);
});

it('detects overnight shifts by end before start', function (): void {
    $night = AttendanceShift::factory()->overnight()->create(['branch_id' => $this->branch->id]);
    $day = AttendanceShift::factory()->create(['branch_id' => $this->branch->id]);

    expect($night->isOvernight())->toBeTrue()
        ->and($day->isOvernight())->toBeFalse();
});

it('detects overlapping assignments for the same staff', function (): void {
    $shift = AttendanceShift::factory()->create(['branch_id' => $this->branch->id]);

    AttendanceShiftAssignment::factory()->create([
        'staff_id' => $this->staff->id,
        'shift_id' => $shift->id,
        'effective_from' => '2026-08-01',
        'effective_to' => null,
    ]);

    $overlap = new AttendanceShiftAssignment([
        'staff_id' => $this->staff->id,
        'shift_id' => $shift->id,
        'effective_from' => '2026-08-10',
        'effective_to' => '2026-08-20',
    ]);

    expect($overlap->overlaps())->toBeTrue();
});

it('does not flag assignments with non-overlapping ranges', function (): void {
    $shift = AttendanceShift::factory()->create(['branch_id' => $this->branch->id]);

    AttendanceShiftAssignment::factory()->create([
        'staff_id' => $this->staff->id,
        'shift_id' => $shift->id,
        'effective_from' => '2026-08-01',
        'effective_to' => '2026-08-10',
    ]);

    $nonOverlap = new AttendanceShiftAssignment([
        'staff_id' => $this->staff->id,
        'shift_id' => $shift->id,
        'effective_from' => '2026-08-11',
        'effective_to' => null,
    ]);

    expect($nonOverlap->overlaps())->toBeFalse();
});

it('does not flag an open-ended assignment against a before-start range', function (): void {
    $shift = AttendanceShift::factory()->create(['branch_id' => $this->branch->id]);

    AttendanceShiftAssignment::factory()->create([
        'staff_id' => $this->staff->id,
        'shift_id' => $shift->id,
        'effective_from' => '2026-08-01',
        'effective_to' => null,
    ]);

    $before = new AttendanceShiftAssignment([
        'staff_id' => $this->staff->id,
        'shift_id' => $shift->id,
        'effective_from' => '2026-07-01',
        'effective_to' => '2026-07-31',
    ]);

    expect($before->overlaps())->toBeFalse();
});

it('ignores soft-deleted assignments when checking overlaps', function (): void {
    $shift = AttendanceShift::factory()->create(['branch_id' => $this->branch->id]);

    $existing = AttendanceShiftAssignment::factory()->create([
        'staff_id' => $this->staff->id,
        'shift_id' => $shift->id,
        'effective_from' => '2026-08-01',
        'effective_to' => null,
    ]);
    $existing->delete();

    $new = new AttendanceShiftAssignment([
        'staff_id' => $this->staff->id,
        'shift_id' => $shift->id,
        'effective_from' => '2026-08-10',
        'effective_to' => null,
    ]);

    expect($new->overlaps())->toBeFalse();
});

it('does not consider a record itself when checking overlaps', function (): void {
    $shift = AttendanceShift::factory()->create(['branch_id' => $this->branch->id]);

    $assignment = AttendanceShiftAssignment::factory()->create([
        'staff_id' => $this->staff->id,
        'shift_id' => $shift->id,
        'effective_from' => '2026-08-01',
        'effective_to' => null,
    ]);

    expect($assignment->overlaps())->toBeFalse();
});

it('marks a machine online when synced within 30 minutes', function (): void {
    $machine = AttendanceMachine::factory()->create([
        'branch_id' => $this->branch->id,
        'last_sync_at' => now()->subMinutes(5),
    ]);

    expect($machine->status)->toBe('online');
});

it('marks a machine offline when last seen more than 30 minutes ago', function (): void {
    $machine = AttendanceMachine::factory()->create([
        'branch_id' => $this->branch->id,
        'last_sync_at' => now()->subHours(2),
    ]);

    expect($machine->status)->toBe('offline');
});

it('marks a machine unknown when it has never been seen', function (): void {
    $machine = AttendanceMachine::factory()->create([
        'branch_id' => $this->branch->id,
        'last_sync_at' => null,
        'last_seen_at' => null,
    ]);

    expect($machine->status)->toBe('unknown');
});
