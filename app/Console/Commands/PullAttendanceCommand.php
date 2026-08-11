<?php

namespace Modules\Attendance\Console\Commands;

use Illuminate\Console\Command;
use Modules\Attendance\Classes\Services\AttendancePullService;
use Modules\Attendance\Models\AttendanceMachine;

class PullAttendanceCommand extends Command
{
    protected $signature = 'attendance:pull {--machine= : Limit to a single machine UUID}';

    protected $description = 'Pull attendance logs from ZKTeco devices';

    public function handle(AttendancePullService $service): int
    {
        $machines = AttendanceMachine::query()
            ->where('is_active', true)
            ->where('pull_enabled', true)
            ->when($this->option('machine'), fn ($query, $id) => $query->where('id', $id))
            ->get();

        if ($machines->isEmpty()) {
            $this->info('No pull-enabled machines found.');

            return self::SUCCESS;
        }

        $failures = 0;
        foreach ($machines as $machine) {
            $this->info("Syncing {$machine->name} ({$machine->serial_number})...");
            if (! $service->sync($machine)) {
                $failures++;
            }
        }

        return $failures === 0 ? self::SUCCESS : self::FAILURE;
    }
}
