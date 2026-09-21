<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Str;
use Modules\Attendance\Filament\Resources\AttendanceMachineResource;
use Modules\Attendance\Filament\Resources\AttendanceMachineResource\Pages\CreateAttendanceMachine;
use Modules\Attendance\Filament\Resources\AttendanceMachineResource\Pages\EditAttendanceMachine;
use Modules\Attendance\Filament\Resources\AttendanceMachineResource\Pages\ListAttendanceMachines;
use Modules\Attendance\Models\AttendanceMachine;
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
    'resource' => AttendanceMachineResource::class,
    'subject' => 'AttendanceMachine',
    'model' => AttendanceMachine::class,
    'listPage' => ListAttendanceMachines::class,
    'createPage' => CreateAttendanceMachine::class,
    'editPage' => EditAttendanceMachine::class,
    'searchColumn' => 'serial_number',
    'sortColumn' => 'name',
    'hasBulkDelete' => false,
    'hasRecordDelete' => true,
    'softDeletes' => true,
    'uniqueField' => 'serial_number',
    'userAttributes' => fn (TestCase $test): array => ['branch_id' => $test->branch->id],
    'makeRecord' => fn (TestCase $test, array $attributes = []): AttendanceMachine => AttendanceMachine::factory()->create([
        'branch_id' => $test->branch->id,
        'name' => fake()->unique()->words(2, true).' Terminal',
        ...$attributes,
    ]),
    'makeRecords' => function (TestCase $test, int $count) {
        return collect(range(1, $count))->map(fn (int $offset): AttendanceMachine => AttendanceMachine::factory()->create([
            'branch_id' => $test->branch->id,
            'name' => 'UniqueTerminal'.$offset,
            'serial_number' => 'SNU'.str_pad((string) $offset, 10, '0', STR_PAD_LEFT),
        ]));
    },
    'createForm' => fn (): array => [
        'name' => fake()->unique()->words(2, true).' Terminal',
        'serial_number' => strtoupper(fake()->unique()->bothify('###??####')),
        'ip_address' => '10.0.0.'.fake()->numberBetween(2, 250),
        'port' => 4370,
        'timezone' => config('app.timezone'),
        'is_active' => true,
    ],
    'updateForm' => fn (): array => [
        'name' => 'Updated '.fake()->unique()->words(2, true),
        'serial_number' => strtoupper(fake()->unique()->bothify('###??####')),
        'ip_address' => '10.1.0.'.fake()->numberBetween(2, 250),
        'port' => 4370,
        'timezone' => config('app.timezone'),
    ],
    'schemaState' => fn (mixed $test, AttendanceMachine $record): array => [
        'name' => $record->name,
        'serial_number' => $record->serial_number,
        'ip_address' => $record->ip_address,
    ],
    'requiredValidation' => [
        'name is required' => [['name' => null], ['name' => 'required']],
        'serial number is required' => [['serial_number' => null], ['serial_number' => 'required']],
        'name is max 100' => [['name' => Str::random(101)], ['name' => 'max']],
    ],
    'databaseHasOnCreate' => fn (mixed $test, array $payload): array => [
        'name' => $payload['name'],
        'serial_number' => $payload['serial_number'],
        'ip_address' => $payload['ip_address'],
    ],
]);
