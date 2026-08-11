<?php

namespace Modules\Attendance\Jobs;

use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Modules\Attendance\Classes\Services\AttendanceDailyService;

class RecomputeDailyAttendanceJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        public string $branchId,
        public string $staffId,
        public string $workDate,
    ) {}

    public function handle(AttendanceDailyService $service): void
    {
        $service->computeForDate(Carbon::parse($this->workDate), $this->branchId, $this->staffId);
    }
}
