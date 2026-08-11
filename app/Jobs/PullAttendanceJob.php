<?php

namespace Modules\Attendance\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Modules\Attendance\Classes\Services\AttendancePullService;
use Modules\Attendance\Models\AttendanceMachine;

class PullAttendanceJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(public string $machineId) {}

    public function handle(AttendancePullService $service): void
    {
        $machine = AttendanceMachine::query()->find($this->machineId);

        if ($machine) {
            $service->sync($machine);
        }
    }
}
