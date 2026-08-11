<?php

use Illuminate\Testing\TestResponse;
use Modules\Attendance\Models\AttendanceMachine;
use Modules\Attendance\Models\AttendanceRecord;
use Modules\Core\Models\Branch;
use Tests\TestCase;

uses(TestCase::class);

beforeEach(function (): void {
    $this->migrateModules(['Core', 'Staff', 'Attendance']);
    $this->branch = Branch::factory()->create();
});

function pushAttlog(string $url, string $body): TestResponse
{
    return test()->call('POST', $url, [], [], [], [
        'CONTENT_TYPE' => 'text/plain',
        'HTTP_CONTENT_LENGTH' => (string) strlen($body),
    ], $body);
}

it('acknowledges a valid ATTLOG push with OK', function (): void {
    AttendanceMachine::factory()->create([
        'branch_id' => $this->branch->id,
        'serial_number' => 'SN123456',
        'push_enabled' => true,
        'is_active' => true,
    ]);

    $body = "1001\t2026-08-10 08:00:00\t0\t1\n1002\t2026-08-10 08:05:00\t1\t2";

    $response = pushAttlog('/iclock/cdata?SN=SN123456&table=ATTLOG', $body);

    $response->assertOk()->assertContent('OK');

    expect(AttendanceRecord::query()->count())->toBe(2);
});

it('updates last_seen_at on contact from a known machine', function (): void {
    $machine = AttendanceMachine::factory()->create([
        'branch_id' => $this->branch->id,
        'serial_number' => 'SN123456',
        'push_enabled' => true,
        'is_active' => true,
        'last_seen_at' => now()->subDay(),
    ]);

    $this->get('/iclock/getrequest?SN=SN123456')->assertOk()->assertContent('OK');

    $this->assertDatabaseHas('attendance_machines', [
        'id' => $machine->id,
        'serial_number' => 'SN123456',
    ]);

    expect($machine->fresh()->last_seen_at)->not->toBeNull();
});

it('drops an unknown serial but responds OK (device-friendly)', function (): void {
    $body = "1001\t2026-08-10 08:00:00\t0\t1";

    $response = pushAttlog('/iclock/cdata?SN=UNKNOWN&table=ATTLOG', $body);

    $response->assertOk()->assertContent('OK');

    expect(AttendanceRecord::query()->count())->toBe(0);
});

it('ignores pushes from a machine that is not push-enabled', function (): void {
    AttendanceMachine::factory()->create([
        'branch_id' => $this->branch->id,
        'serial_number' => 'SN123456',
        'push_enabled' => false,
        'is_active' => true,
    ]);

    $body = "1001\t2026-08-10 08:00:00\t0\t1";

    $response = pushAttlog('/iclock/cdata?SN=SN123456&table=ATTLOG', $body);

    $response->assertOk();

    expect(AttendanceRecord::query()->count())->toBe(0);
});

it('dedups a repeated ATTLOG push', function (): void {
    AttendanceMachine::factory()->create([
        'branch_id' => $this->branch->id,
        'serial_number' => 'SN123456',
        'push_enabled' => true,
        'is_active' => true,
    ]);

    $body = "1001\t2026-08-10 08:00:00\t0\t1";

    pushAttlog('/iclock/cdata?SN=SN123456&table=ATTLOG', $body)->assertOk();
    pushAttlog('/iclock/cdata?SN=SN123456&table=ATTLOG', $body)->assertOk();

    expect(AttendanceRecord::query()->count())->toBe(1);
});

it('acknowledges non-ATTLOG tables without recording', function (): void {
    AttendanceMachine::factory()->create([
        'branch_id' => $this->branch->id,
        'serial_number' => 'SN123456',
        'push_enabled' => true,
        'is_active' => true,
    ]);

    $response = pushAttlog('/iclock/cdata?SN=SN123456&table=OPERLOG', 'whatever');

    $response->assertOk();

    expect(AttendanceRecord::query()->count())->toBe(0);
});

it('rejects an oversized ATTLOG body', function (): void {
    AttendanceMachine::factory()->create([
        'branch_id' => $this->branch->id,
        'serial_number' => 'SN123456',
        'push_enabled' => true,
        'is_active' => true,
    ]);

    $body = str_repeat('x', 10_485_761);

    $response = test()->call('POST', '/iclock/cdata?SN=SN123456&table=ATTLOG', [], [], [], [
        'CONTENT_TYPE' => 'text/plain',
    ], $body);

    $response->assertOk();

    expect(AttendanceRecord::query()->count())->toBe(0);
});

it('handles GET cdata as a handshake', function (): void {
    AttendanceMachine::factory()->create([
        'branch_id' => $this->branch->id,
        'serial_number' => 'SN123456',
        'push_enabled' => true,
        'is_active' => true,
    ]);

    $this->get('/iclock/cdata?SN=SN123456')->assertOk()->assertContent('OK');
});
