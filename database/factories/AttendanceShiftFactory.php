<?php

namespace Modules\Attendance\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Attendance\Models\AttendanceShift;
use Modules\Core\Models\Branch;

/** @extends Factory<AttendanceShift> */
class AttendanceShiftFactory extends Factory
{
    protected $model = AttendanceShift::class;

    public function definition(): array
    {
        return [
            'branch_id' => Branch::factory(),
            'name' => $this->faker->randomElement(['Morning', 'Evening', 'Night']),
            'start_time' => '08:00:00',
            'end_time' => '17:00:00',
            'late_grace_minutes' => 15,
            'overtime_after_minutes' => 60,
            'break_minutes' => 60,
            'is_active' => true,
        ];
    }

    public function overnight(): static
    {
        return $this->state(fn (): array => [
            'start_time' => '22:00:00',
            'end_time' => '06:00:00',
        ]);
    }
}
