<?php

namespace Modules\Attendance\Classes\Services;

use Illuminate\Support\Facades\Log;
use Jmrashed\Zkteco\Lib\ZKTeco;
use Modules\Attendance\Enums\PunchSource;
use Modules\Attendance\Enums\PunchType;
use Modules\Attendance\Models\AttendanceMachine;
use RuntimeException;
use Throwable;

class AttendancePullService
{
    public function __construct(private AttendanceIngestionService $ingestion) {}

    public function testConnection(AttendanceMachine $machine): bool
    {
        if (! $machine->ip_address) {
            return false;
        }

        try {
            // v2.1.0: no comm-key arg; constructor throws if socket create/setopt fails.
            $device = new ZKTeco($machine->ip_address, (int) $machine->port);
        } catch (Throwable) {
            return false;
        }

        try {
            $ok = $device->connect();

            return $ok;
        } catch (Throwable) {
            return false;
        } finally {
            try {
                $device->disconnect();
            } catch (Throwable) {
                // ignore
            }
        }
    }

    public function sync(AttendanceMachine $machine): bool
    {
        if (! $machine->ip_address) {
            Log::warning('Attendance pull skipped: machine has no IP', ['machine' => $machine->id]);

            return false;
        }

        try {
            $device = new ZKTeco($machine->ip_address, (int) $machine->port);
        } catch (Throwable) {
            Log::error('Attendance pull failed: could not create device client', ['machine' => $machine->id]);

            return false;
        }

        try {
            if (! $device->connect()) {
                throw new RuntimeException('Unable to connect to device');
            }

            $records = $device->getSanitizedAttendance();
            $punches = [];
            foreach ($records as $record) {
                $record = (array) $record;
                $timestamp = (string) ($record['timestamp'] ?? '');
                $badge = (string) ($record['id'] ?? $record['uid'] ?? '');
                if ($timestamp === '' || $badge === '') {
                    continue;
                }
                $punches[] = [
                    'badge_number' => $badge,
                    'punched_at' => $timestamp,
                    'punch_type' => PunchType::fromStatus((int) ($record['state'] ?? -1)),
                    'verify_type' => null,
                    'status_code' => isset($record['type']) && $record['type'] !== '' ? (int) $record['type'] : null,
                    'raw_line' => json_encode($record),
                ];
            }

            $this->ingestion->ingest($machine, $machine->branch_id, $punches, PunchSource::Pull);

            $machine->forceFill(['last_sync_at' => now(), 'last_seen_at' => now()])->saveQuietly();

            return true;
        } catch (Throwable $e) {
            Log::error('Attendance pull failed', ['machine' => $machine->id, 'error' => $e->getMessage()]);

            return false;
        } finally {
            try {
                $device->disconnect();
            } catch (Throwable) {
                // ignore
            }
        }
    }
}
