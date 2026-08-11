<?php

namespace Modules\Attendance\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Attendance\Enums\AttendanceStatus;
use Modules\Attendance\Models\DailyAttendance;
use Modules\Staff\Models\Staff;

/** @extends Factory<DailyAttendance> */
class DailyAttendanceFactory extends Factory
{
    protected $model = DailyAttendance::class;

    public function definition(): array
    {
        $staff = Staff::factory()->create();

        return [
            'branch_id' => $staff->branch_id,
            'staff_id' => $staff->id,
            'work_date' => now()->toDateString(),
            'status' => AttendanceStatus::Present,
            'worked_minutes' => 0,
            'late_minutes' => 0,
            'overtime_minutes' => 0,
        ];
    }
}
