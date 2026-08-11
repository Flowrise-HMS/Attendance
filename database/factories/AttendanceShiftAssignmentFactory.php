<?php

namespace Modules\Attendance\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Attendance\Models\AttendanceShift;
use Modules\Attendance\Models\AttendanceShiftAssignment;
use Modules\Staff\Models\Staff;

/** @extends Factory<AttendanceShiftAssignment> */
class AttendanceShiftAssignmentFactory extends Factory
{
    protected $model = AttendanceShiftAssignment::class;

    public function definition(): array
    {
        return [
            'staff_id' => Staff::factory(),
            'shift_id' => AttendanceShift::factory(),
            'effective_from' => now()->startOfMonth()->toDateString(),
            'effective_to' => null,
        ];
    }
}
