<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Modules\Attendance\Enums\AttendanceStatus;
use Modules\Attendance\Filament\Resources\DailyAttendanceResource;
use Modules\Attendance\Filament\Resources\DailyAttendanceResource\Pages\ListDailyAttendances;
use Modules\Attendance\Models\DailyAttendance;
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
    'resource' => DailyAttendanceResource::class,
    'subject' => 'DailyAttendance',
    'model' => DailyAttendance::class,
    'listPage' => ListDailyAttendances::class,
    'sortColumn' => 'work_date',
    'filter' => [
        'name' => 'status',
        'value' => AttendanceStatus::Present->value,
        'attribute' => 'status',
    ],
    'hasBulkDelete' => false,
    'hasRecordDelete' => false,
    'userAttributes' => fn (TestCase $test): array => ['branch_id' => $test->branch->id],
    'makeRecord' => function (TestCase $test, array $attributes = []): DailyAttendance {
        $staff = Staff::factory()->create(['branch_id' => $test->branch->id]);

        return DailyAttendance::factory()->create([
            'branch_id' => $test->branch->id,
            'staff_id' => $staff->id,
            'work_date' => now()->subDays(fake()->unique()->numberBetween(1, 60))->toDateString(),
            ...$attributes,
        ]);
    },
    'makeRecords' => function (TestCase $test, int $count) {
        return collect(range(1, $count))->map(function (int $offset) use ($test): DailyAttendance {
            $staff = Staff::factory()->create(['branch_id' => $test->branch->id]);
            $status = $offset === 1 ? AttendanceStatus::Present : AttendanceStatus::Absent;

            return DailyAttendance::factory()->create([
                'branch_id' => $test->branch->id,
                'staff_id' => $staff->id,
                'work_date' => now()->subDays($offset)->toDateString(),
                'status' => $status,
            ]);
        });
    },
]);
