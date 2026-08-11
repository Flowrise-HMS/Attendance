<?php

use Illuminate\Support\Facades\Bus;
use Modules\Attendance\Classes\Services\AttendanceIngestionService;
use Modules\Attendance\Enums\PunchSource;
use Modules\Attendance\Enums\PunchType;
use Modules\Attendance\Enums\VerifyType;
use Modules\Attendance\Jobs\RecomputeDailyAttendanceJob;
use Modules\Attendance\Models\AttendanceMachine;
use Modules\Attendance\Models\AttendanceRecord;
use Modules\Core\Models\Branch;
use Modules\Staff\Models\Staff;
use Tests\TestCase;

uses(TestCase::class);

beforeEach(function (): void {
    $this->migrateModules(['Core', 'Staff', 'Attendance']);
    $this->branch = Branch::factory()->create();
    $this->service = app(AttendanceIngestionService::class);
});

it('resolves badge to staff via zk_user_id first', function (): void {
    $staff = Staff::factory()->create([
        'branch_id' => $this->branch->id,
        'zk_user_id' => 'ZKT-001',
        'staff_number' => 'STF-00001',
    ]);

    $inserted = $this->service->ingest(null, $this->branch->id, [
        ['badge_number' => 'ZKT-001', 'punched_at' => '2026-08-10 08:00:00', 'punch_type' => PunchType::In],
    ], PunchSource::Push);

    expect($inserted)->toBe(1);
    $this->assertDatabaseHas('attendance_records', [
        'staff_id' => $staff->id,
        'badge_number' => 'ZKT-001',
    ]);
});

it('resolves badge to staff via staff_number as fallback', function (): void {
    $staff = Staff::factory()->create([
        'branch_id' => $this->branch->id,
        'zk_user_id' => null,
        'staff_number' => 'STF-2026-00042',
    ]);

    $inserted = $this->service->ingest(null, $this->branch->id, [
        ['badge_number' => 'STF-2026-00042', 'punched_at' => '2026-08-10 08:00:00', 'punch_type' => PunchType::In],
    ], PunchSource::Push);

    expect($inserted)->toBe(1);
    $this->assertDatabaseHas('attendance_records', [
        'staff_id' => $staff->id,
    ]);
});

it('stores unmapped punches with null staff_id', function (): void {
    $inserted = $this->service->ingest(null, $this->branch->id, [
        ['badge_number' => 'GHOST-9', 'punched_at' => '2026-08-10 08:00:00', 'punch_type' => PunchType::In],
    ], PunchSource::Push);

    expect($inserted)->toBe(1);
    $this->assertDatabaseHas('attendance_records', [
        'badge_number' => 'GHOST-9',
        'staff_id' => null,
        'source' => PunchSource::Push->value,
    ]);
});

it('converts machine-local timestamps to the app timezone', function (): void {
    config(['app.timezone' => 'UTC']);

    $machine = AttendanceMachine::factory()->create([
        'branch_id' => $this->branch->id,
        'timezone' => 'Africa/Accra',
    ]);

    $this->service->ingest($machine, $this->branch->id, [
        ['badge_number' => '1001', 'punched_at' => '2026-08-10 08:00:00', 'punch_type' => PunchType::In],
    ], PunchSource::Push);

    $this->assertDatabaseHas('attendance_records', [
        'machine_id' => $machine->id,
        'badge_number' => '1001',
        'punched_at' => '2026-08-10 08:00:00',
    ]);
});

it('stores punch metadata fields correctly', function (): void {
    $inserted = $this->service->ingest(null, $this->branch->id, [
        [
            'badge_number' => '1001',
            'punched_at' => '2026-08-10 08:00:00',
            'punch_type' => PunchType::Out,
            'verify_type' => VerifyType::Fingerprint,
            'status_code' => 1,
            'raw_line' => "1001\t2026-08-10 08:00:00\t1\t1",
        ],
    ], PunchSource::Push);

    expect($inserted)->toBe(1);
    $this->assertDatabaseHas('attendance_records', [
        'badge_number' => '1001',
        'punch_type' => PunchType::Out->value,
        'verify_type' => VerifyType::Fingerprint->value,
        'status_code' => 1,
        'raw_line' => "1001\t2026-08-10 08:00:00\t1\t1",
        'source' => PunchSource::Push->value,
    ]);
});

it('dedups the same physical punch across sources', function (): void {
    $punch = ['badge_number' => '1001', 'punched_at' => '2026-08-10 08:00:00', 'punch_type' => PunchType::In];

    $first = $this->service->ingest(null, $this->branch->id, [$punch], PunchSource::Push);
    $second = $this->service->ingest(null, $this->branch->id, [$punch], PunchSource::Pull);
    $third = $this->service->ingest(null, $this->branch->id, [$punch], PunchSource::Import);

    expect($first)->toBe(1)
        ->and($second)->toBe(0)
        ->and($third)->toBe(0);

    expect(AttendanceRecord::query()->where('badge_number', '1001')->count())->toBe(1);
});

it('treats punches from different machines as distinct records', function (): void {
    $machineA = AttendanceMachine::factory()->create(['branch_id' => $this->branch->id]);
    $machineB = AttendanceMachine::factory()->create(['branch_id' => $this->branch->id]);

    $punchA = ['badge_number' => '1001', 'punched_at' => '2026-08-10 08:00:00', 'punch_type' => PunchType::In];
    $punchB = ['badge_number' => '1001', 'punched_at' => '2026-08-10 08:00:00', 'punch_type' => PunchType::In];

    $this->service->ingest($machineA, $this->branch->id, [$punchA], PunchSource::Push);
    $this->service->ingest($machineB, $this->branch->id, [$punchB], PunchSource::Push);

    expect(AttendanceRecord::query()->where('badge_number', '1001')->count())->toBe(2);
});

it('skips invalid rows without aborting the batch', function (): void {
    $inserted = $this->service->ingest(null, $this->branch->id, [
        ['badge_number' => '', 'punched_at' => '2026-08-10 08:00:00'],
        ['badge_number' => '1001', 'punched_at' => '2026-08-10 08:00:00', 'punch_type' => PunchType::In],
    ], PunchSource::Push);

    expect($inserted)->toBe(1);
    expect(AttendanceRecord::query()->count())->toBe(1);
});

it('re-ingests a punch after hard delete creates a fresh row', function (): void {
    $punch = ['badge_number' => '1001', 'punched_at' => '2026-08-10 08:00:00', 'punch_type' => PunchType::In];

    $this->service->ingest(null, $this->branch->id, [$punch], PunchSource::Push);
    expect(AttendanceRecord::query()->count())->toBe(1);

    $record = AttendanceRecord::query()->first();
    $hash = $record->dedup_hash;
    $record->delete();

    $inserted = $this->service->ingest(null, $this->branch->id, [$punch], PunchSource::Push);

    expect($inserted)->toBe(1)
        ->and(AttendanceRecord::query()->count())->toBe(1)
        ->and(AttendanceRecord::query()->first()->dedup_hash)->toBe($hash);
});

it('dispatches recompute jobs for the punch date and the previous date', function (): void {
    Bus::fake();

    $staff = Staff::factory()->create([
        'branch_id' => $this->branch->id,
        'zk_user_id' => 'ZKT-001',
    ]);

    $this->service->ingest(null, $this->branch->id, [
        ['badge_number' => 'ZKT-001', 'punched_at' => '2026-08-11 00:30:00', 'punch_type' => PunchType::In],
    ], PunchSource::Push);

    Bus::assertDispatched(RecomputeDailyAttendanceJob::class, fn (RecomputeDailyAttendanceJob $job) => $job->staffId === $staff->id && $job->workDate === '2026-08-11');
    Bus::assertDispatched(RecomputeDailyAttendanceJob::class, fn (RecomputeDailyAttendanceJob $job) => $job->staffId === $staff->id && $job->workDate === '2026-08-10');
});

it('deduplicates recompute jobs across a large batch', function (): void {
    Bus::fake();

    $staff = Staff::factory()->create([
        'branch_id' => $this->branch->id,
        'zk_user_id' => 'ZKT-001',
    ]);

    $punches = collect(range(0, 4))->map(fn (int $i) => [
        'badge_number' => 'ZKT-001',
        'punched_at' => "2026-08-11 0{$i}:00:00",
        'punch_type' => PunchType::In,
    ])->all();

    $this->service->ingest(null, $this->branch->id, $punches, PunchSource::Pull);

    Bus::assertDispatchedTimes(RecomputeDailyAttendanceJob::class, 2);
});

it('parses ATTLOG multi-line body into punches', function (): void {
    $machine = AttendanceMachine::factory()->create(['branch_id' => $this->branch->id]);

    $body = "1001\t2026-08-10 08:00:00\t0\t1\n1002\t2026-08-10 08:05:00\t1\t2";

    $inserted = $this->service->ingestAttlog($machine, $body);

    expect($inserted)->toBe(2);
    $this->assertDatabaseHas('attendance_records', [
        'badge_number' => '1001',
        'punch_type' => PunchType::In->value,
        'verify_type' => VerifyType::Fingerprint->value,
    ]);
    $this->assertDatabaseHas('attendance_records', [
        'badge_number' => '1002',
        'punch_type' => PunchType::Out->value,
        'verify_type' => VerifyType::Card->value,
    ]);
});

it('skips malformed ATTLOG lines', function (): void {
    $machine = AttendanceMachine::factory()->create(['branch_id' => $this->branch->id]);

    $body = "1001\t2026-08-10 08:00:00\t0\t1\nMALFORMED";

    $inserted = $this->service->ingestAttlog($machine, $body);

    expect($inserted)->toBe(1);
});
