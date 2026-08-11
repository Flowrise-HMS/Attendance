<?php

namespace Modules\Attendance\Console\Commands;

use Carbon\Carbon;
use Illuminate\Console\Command;
use Modules\Attendance\Classes\Services\AttendanceDailyService;

class ComputeDailyAttendanceCommand extends Command
{
    protected $signature = 'attendance:compute-daily {--date= : Work date (Y-m-d), defaults to yesterday} {--staff= : Restrict to one staff UUID} {--branch= : Restrict to one branch UUID}';

    protected $description = 'Recompute daily attendance summaries';

    public function handle(AttendanceDailyService $service): int
    {
        $date = $this->option('date') ? Carbon::parse($this->option('date')) : Carbon::yesterday();
        $service->computeForDate($date, $this->option('branch'), $this->option('staff'));

        $this->info("Daily attendance computed for {$date->toDateString()}.");

        return self::SUCCESS;
    }
}
