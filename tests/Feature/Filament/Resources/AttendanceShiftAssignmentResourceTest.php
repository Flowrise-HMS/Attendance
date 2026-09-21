<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Modules\Attendance\Filament\Resources\AttendanceShiftAssignmentResource;
use Modules\Attendance\Filament\Resources\AttendanceShiftAssignmentResource\Pages\CreateAttendanceShiftAssignment;
use Modules\Attendance\Filament\Resources\AttendanceShiftAssignmentResource\Pages\EditAttendanceShiftAssignment;
use Modules\Attendance\Filament\Resources\AttendanceShiftAssignmentResource\Pages\ListAttendanceShiftAssignments;
use Modules\Attendance\Models\AttendanceShift;
use Modules\Attendance\Models\AttendanceShiftAssignment;
use Modules\Core\Models\Branch;
use Modules\Staff\Models\Staff;
use Tests\Support\FilamentResourceTestSuite;
use Tests\TestCase;

uses(TestCase::class, DatabaseTransactions::class);

beforeEach(function (): void {
    $this->requireModule('Attendance');
    $this->migrateModules(['Core', 'Staff', 'Attendance']);
    $this->branch = Branch::factory()->create();
    $this->setCurrentBranch($this->branch);
});

FilamentResourceTestSuite::register([
    'resource' => AttendanceShiftAssignmentResource::class,
    'subject' => 'AttendanceShiftAssignment',
    'model' => AttendanceShiftAssignment::class,
    'listPage' => ListAttendanceShiftAssignments::class,
    'createPage' => CreateAttendanceShiftAssignment::class,
    'editPage' => EditAttendanceShiftAssignment::class,
    'sortColumn' => 'effective_from',
    'hasBulkDelete' => false,
    'hasRecordDelete' => true,
    'softDeletes' => true,
    'userAttributes' => fn (TestCase $test): array => ['branch_id' => $test->branch->id],
    'makeRecord' => function (TestCase $test, array $attributes = []): AttendanceShiftAssignment {
        unset($attributes['branch_id']);

        $staff = Staff::factory()->create(['branch_id' => $test->branch->id]);
        $shift = AttendanceShift::factory()->create(['branch_id' => $test->branch->id]);

        return AttendanceShiftAssignment::factory()->create([
            'staff_id' => $staff->id,
            'shift_id' => $shift->id,
            'effective_from' => now()->subDays(fake()->unique()->numberBetween(1, 200))->toDateString(),
            ...$attributes,
        ]);
    },
    'makeRecords' => function (TestCase $test, int $count) {
        return collect(range(1, $count))->map(function (int $offset) use ($test): AttendanceShiftAssignment {
            $staff = Staff::factory()->create(['branch_id' => $test->branch->id]);
            $shift = AttendanceShift::factory()->create([
                'branch_id' => $test->branch->id,
                'name' => 'Assignment Shift '.$offset,
            ]);

            return AttendanceShiftAssignment::factory()->create([
                'staff_id' => $staff->id,
                'shift_id' => $shift->id,
                'effective_from' => now()->subDays($offset)->toDateString(),
            ]);
        });
    },
    'createForm' => function (TestCase $test): array {
        $staff = Staff::factory()->create(['branch_id' => $test->branch->id]);
        $shift = AttendanceShift::factory()->create(['branch_id' => $test->branch->id, 'name' => 'Create Shift']);

        return [
            'staff_id' => $staff->id,
            'shift_id' => $shift->id,
            'effective_from' => now()->addWeek()->toDateString(),
        ];
    },
    'updateForm' => function (TestCase $test, AttendanceShiftAssignment $record): array {
        $shift = AttendanceShift::factory()->create(['branch_id' => $test->branch->id, 'name' => 'Updated Shift']);

        return [
            'staff_id' => $record->staff_id,
            'shift_id' => $shift->id,
            'effective_from' => now()->addDays(10)->toDateString(),
        ];
    },
    'schemaState' => fn (mixed $test, AttendanceShiftAssignment $record): array => [
        'staff_id' => $record->staff_id,
        'shift_id' => $record->shift_id,
    ],
    'requiredValidation' => [
        'staff is required' => [['staff_id' => null], ['staff_id' => 'required']],
        'shift is required' => [['shift_id' => null], ['shift_id' => 'required']],
        'effective from is required' => [['effective_from' => null], ['effective_from' => 'required']],
    ],
    'databaseHasOnCreate' => fn (mixed $test, array $payload): array => [
        'staff_id' => $payload['staff_id'],
        'shift_id' => $payload['shift_id'],
    ],
]);
