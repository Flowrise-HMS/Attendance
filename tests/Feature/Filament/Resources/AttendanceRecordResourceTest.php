<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Modules\Attendance\Enums\PunchSource;
use Modules\Attendance\Enums\PunchType;
use Modules\Attendance\Filament\Resources\AttendanceRecordResource;
use Modules\Attendance\Filament\Resources\AttendanceRecordResource\Pages\EditAttendanceRecord;
use Modules\Attendance\Filament\Resources\AttendanceRecordResource\Pages\ListAttendanceRecords;
use Modules\Attendance\Models\AttendanceMachine;
use Modules\Attendance\Models\AttendanceRecord;
use Modules\Core\Models\Branch;
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
    'resource' => AttendanceRecordResource::class,
    'subject' => 'AttendanceRecord',
    'model' => AttendanceRecord::class,
    'listPage' => ListAttendanceRecords::class,
    'editPage' => EditAttendanceRecord::class,
    'searchColumn' => 'badge_number',
    'sortColumn' => 'punched_at',
    'filter' => [
        'name' => 'punch_type',
        'value' => PunchType::In->value,
        'attribute' => 'punch_type',
    ],
    'hasBulkDelete' => false,
    'hasRecordDelete' => true,
    'userAttributes' => fn (TestCase $test): array => ['branch_id' => $test->branch->id],
    'makeRecord' => function (TestCase $test, array $attributes = []): AttendanceRecord {
        $machine = AttendanceMachine::factory()->create(['branch_id' => $test->branch->id]);
        $badge = (string) fake()->unique()->numberBetween(1000, 9999);
        $punchedAt = now()->subHours(fake()->unique()->numberBetween(1, 200));

        return AttendanceRecord::factory()->forMachine($machine, $badge, $punchedAt)->create($attributes);
    },
    'makeRecords' => function (TestCase $test, int $count) {
        $machine = AttendanceMachine::factory()->create(['branch_id' => $test->branch->id]);

        return collect(range(1, $count))->map(function (int $offset) use ($machine): AttendanceRecord {
            $badge = (string) (2000 + $offset);
            $punchedAt = now()->subHours($offset);
            $type = $offset === 1 ? PunchType::In : PunchType::Out;

            return AttendanceRecord::factory()->forMachine($machine, $badge, $punchedAt)->create([
                'punch_type' => $type,
            ]);
        });
    },
    'updateForm' => fn (): array => [
        'badge_number' => (string) fake()->unique()->numberBetween(4000, 4999),
        'punched_at' => now()->subHour()->toDateTimeString(),
        'punch_type' => PunchType::Out->value,
        'source' => PunchSource::Manual->value,
    ],
    'schemaState' => fn (mixed $test, AttendanceRecord $record): array => [
        'badge_number' => $record->badge_number,
        'punch_type' => $record->punch_type,
        'source' => $record->source,
    ],
]);
