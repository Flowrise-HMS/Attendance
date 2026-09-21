<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Str;
use Modules\Attendance\Filament\Resources\AttendanceShiftResource;
use Modules\Attendance\Filament\Resources\AttendanceShiftResource\Pages\CreateAttendanceShift;
use Modules\Attendance\Filament\Resources\AttendanceShiftResource\Pages\EditAttendanceShift;
use Modules\Attendance\Filament\Resources\AttendanceShiftResource\Pages\ListAttendanceShifts;
use Modules\Attendance\Models\AttendanceShift;
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
    'resource' => AttendanceShiftResource::class,
    'subject' => 'AttendanceShift',
    'model' => AttendanceShift::class,
    'listPage' => ListAttendanceShifts::class,
    'createPage' => CreateAttendanceShift::class,
    'editPage' => EditAttendanceShift::class,
    'searchColumn' => 'name',
    'sortColumn' => 'name',
    'hasBulkDelete' => false,
    'hasRecordDelete' => true,
    'softDeletes' => true,
    'userAttributes' => fn (TestCase $test): array => ['branch_id' => $test->branch->id],
    'makeRecord' => fn (TestCase $test, array $attributes = []): AttendanceShift => AttendanceShift::factory()->create([
        'branch_id' => $test->branch->id,
        'name' => fake()->unique()->words(2, true).' Shift',
        ...$attributes,
    ]),
    'makeRecords' => fn (TestCase $test, int $count) => AttendanceShift::factory()->count($count)->sequence(
        fn ($sequence) => ['name' => 'Shift '.$sequence->index, 'branch_id' => $test->branch->id],
    )->create(['branch_id' => $test->branch->id]),
    'createForm' => fn (): array => [
        'name' => fake()->unique()->words(2, true).' Shift',
        'start_time' => '08:00',
        'end_time' => '17:00',
        'late_grace_minutes' => 15,
        'is_active' => true,
    ],
    'updateForm' => fn (): array => [
        'name' => 'Updated '.fake()->unique()->words(2, true),
        'start_time' => '09:00',
        'end_time' => '18:00',
    ],
    'schemaState' => fn (mixed $test, AttendanceShift $record): array => [
        'name' => $record->name,
    ],
    'requiredValidation' => [
        'name is required' => [['name' => null], ['name' => 'required']],
        'start time is required' => [['start_time' => null], ['start_time' => 'required']],
        'name is max 100' => [['name' => Str::random(101)], ['name' => 'max']],
    ],
    'databaseHasOnCreate' => fn (mixed $test, array $payload): array => [
        'name' => $payload['name'],
    ],
]);

it('assigns the default branch when a branchless user creates a shift without a selected branch', function (): void {
    $defaultBranch = Branch::factory()->create(['is_default' => true]);
    Branch::query()->whereKeyNot($defaultBranch->id)->update(['is_default' => false]);
    Illuminate\Support\Facades\Context::forget('current_branch_id');
    session()->forget('current_branch_id');
    app()->forgetInstance('currentBranchId');

    $this->actingAs(App\Models\User::factory()->create(['branch_id' => null]));
    Illuminate\Support\Facades\Gate::before(fn (): bool => true);

    Livewire\Livewire::test(CreateAttendanceShift::class)
        ->fillForm([
            'name' => 'UI QA Day',
            'start_time' => '08:00',
            'end_time' => '17:00',
            'late_grace_minutes' => 15,
            'is_active' => true,
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    expect(AttendanceShift::query()->withoutGlobalScopes()->where('name', 'UI QA Day')->value('branch_id'))
        ->toBe($defaultBranch->id);
});
