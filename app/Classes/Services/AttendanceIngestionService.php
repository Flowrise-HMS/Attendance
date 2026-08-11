<?php

namespace Modules\Attendance\Classes\Services;

use Carbon\Carbon;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Modules\Attendance\Enums\PunchSource;
use Modules\Attendance\Enums\PunchType;
use Modules\Attendance\Enums\VerifyType;
use Modules\Attendance\Jobs\RecomputeDailyAttendanceJob;
use Modules\Attendance\Models\AttendanceMachine;
use Modules\Attendance\Models\AttendanceRecord;
use Modules\Staff\Models\Staff;
use Throwable;

class AttendanceIngestionService
{
    /**
     * Store raw punches through the shared pipeline.
     *
     * @param  AttendanceMachine|null  $machine  null for CSV "unassigned" imports
     * @param  array<int, array<string, mixed>>  $rawPunches  each: badge_number, punched_at (machine-local wall clock), punch_type, verify_type, status_code, raw_line
     * @return int number of newly inserted records
     */
    public function ingest(?AttendanceMachine $machine, string $branchId, array $rawPunches, PunchSource $source): int
    {
        $inserted = 0;
        $affectedDates = [];

        $existingHashes = $this->existingHashesFor($rawPunches, $machine);

        $records = [];
        foreach ($rawPunches as $raw) {
            try {
                $record = $this->normalize($raw, $machine, $branchId, $source);
            } catch (Throwable $e) {
                Log::warning('Attendance punch skipped: invalid row', ['error' => $e->getMessage()]);

                continue;
            }

            $hash = $record['dedup_hash'];
            if (isset($existingHashes[$hash])) {
                continue;
            }

            $records[] = $record;
            if ($record['staff_id'] !== null) {
                $affectedDates[] = [$record['staff_id'], $record['punched_at']->format('Y-m-d'), $record['punched_at']->copy()->subDay()->format('Y-m-d')];
            }
        }

        foreach (array_chunk($records, 200) as $chunk) {
            $chunk = array_map(fn ($row) => array_merge($row, [
                'id' => (string) Str::uuid(),
                'created_at' => now(),
                'updated_at' => now(),
            ]), $chunk);

            try {
                AttendanceRecord::insert($chunk);
                $inserted += count($chunk);
            } catch (QueryException $e) {
                if (! $this->isDuplicateKey($e)) {
                    throw $e;
                }
                foreach ($chunk as $row) {
                    if (AttendanceRecord::query()->where('dedup_hash', $row['dedup_hash'])->exists()) {
                        continue;
                    }
                    AttendanceRecord::insert($row);
                    $inserted++;
                }
            }
        }

        $this->dispatchRecomputes($branchId, $affectedDates);

        return $inserted;
    }

    public function ingestAttlog(AttendanceMachine $machine, string $body): int
    {
        $punches = [];
        foreach (preg_split('/\r\n|\r|\n/', $body) as $line) {
            $line = trim($line);
            if ($line === '') {
                continue;
            }
            $parts = explode("\t", $line);
            if (count($parts) < 3) {
                Log::warning('Attendance ATTLOG line skipped', ['line' => $line]);

                continue;
            }
            [$badge, $datetime, $status] = array_map('trim', $parts);
            $verifyType = trim($parts[3] ?? '');

            $punches[] = [
                'badge_number' => $badge,
                'punched_at' => $datetime,
                'punch_type' => PunchType::fromStatus((int) $status),
                'verify_type' => VerifyType::fromCode($verifyType !== '' ? (int) $verifyType : null),
                'status_code' => $status !== '' ? (int) $status : null,
                'raw_line' => $line,
            ];
        }

        return $this->ingest($machine, $machine->branch_id, $punches, PunchSource::Push);
    }

    protected function normalize(array $raw, ?AttendanceMachine $machine, string $branchId, PunchSource $source): array
    {
        $badge = trim((string) ($raw['badge_number'] ?? ''));
        if ($badge === '') {
            throw new \InvalidArgumentException('Missing badge_number');
        }

        $punchedAt = $this->parsePunchedAt($raw['punched_at'] ?? null, $machine);
        $staff = $this->resolveStaff($badge);

        return [
            'branch_id' => $branchId,
            'machine_id' => $machine?->id,
            'staff_id' => $staff?->id,
            'source' => $source->value,
            'badge_number' => $badge,
            'punched_at' => $punchedAt,
            'punch_type' => ($raw['punch_type'] ?? PunchType::Unspecified) instanceof PunchType
                ? ($raw['punch_type'] ?? PunchType::Unspecified)->value
                : (string) ($raw['punch_type'] ?? 'unspecified'),
            'verify_type' => ($raw['verify_type'] ?? null) instanceof VerifyType
                ? ($raw['verify_type'] ?? null)->value
                : ($raw['verify_type'] ?? null),
            'status_code' => isset($raw['status_code']) && $raw['status_code'] !== '' ? (int) $raw['status_code'] : null,
            'raw_line' => $raw['raw_line'] ?? null,
            'dedup_hash' => AttendanceRecord::makeDedupHash($machine?->id ?? 'unassigned', $badge, $punchedAt->format('Y-m-d H:i:s')),
        ];
    }

    protected function parsePunchedAt(mixed $value, ?AttendanceMachine $machine): Carbon
    {
        $timezone = $machine?->timezone ?: config('app.timezone');
        $carbon = $value instanceof Carbon ? $value->copy() : Carbon::parse($value, $timezone);

        if ($machine) {
            $carbon = $carbon->setTimezone(config('app.timezone'));
        }

        return $carbon;
    }

    protected function resolveStaff(string $badge): ?Staff
    {
        return Staff::query()
            ->where('zk_user_id', $badge)
            ->orWhere('staff_number', $badge)
            ->first();
    }

    protected function existingHashesFor(array $rawPunches, ?AttendanceMachine $machine): array
    {
        $hashes = [];
        foreach ($rawPunches as $raw) {
            try {
                $badge = trim((string) ($raw['badge_number'] ?? ''));
                $punchedAt = $this->parsePunchedAt($raw['punched_at'] ?? null, $machine);
                $hashes[] = AttendanceRecord::makeDedupHash($machine?->id ?? 'unassigned', $badge, $punchedAt->format('Y-m-d H:i:s'));
            } catch (Throwable) {
                // ignore invalid rows; normalize() will log them later
            }
        }

        if ($hashes === []) {
            return [];
        }

        return array_fill_keys(AttendanceRecord::query()->whereIn('dedup_hash', $hashes)->pluck('dedup_hash')->all(), true);
    }

    protected function dispatchRecomputes(string $branchId, array $affectedDates): void
    {
        $pairs = [];
        foreach ($affectedDates as [$staffId, $date, $prevDate]) {
            $pairs[$staffId.'|'.$date] = [$staffId, $date];
            $pairs[$staffId.'|'.$prevDate] = [$staffId, $prevDate];
        }

        foreach ($pairs as [$staffId, $date]) {
            RecomputeDailyAttendanceJob::dispatch($branchId, $staffId, $date);
        }
    }

    protected function isDuplicateKey(QueryException $e): bool
    {
        // Restrict to actual duplicate-key SQLSTATEs (MySQL errorInfo[1] 1062/1061).
        // Do NOT fall back on getCode() === '23000' — that also matches FK and NOT-NULL
        // violations, which would silently swallow genuinely failing rows.
        return in_array($e->errorInfo[1] ?? null, [1062, 1061], true);
    }
}
