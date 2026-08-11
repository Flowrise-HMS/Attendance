<?php

namespace Modules\Attendance\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Attendance\Models\AttendanceMachine;
use Modules\Core\Models\Branch;

/** @extends Factory<AttendanceMachine> */
class AttendanceMachineFactory extends Factory
{
    protected $model = AttendanceMachine::class;

    public function definition(): array
    {
        return [
            'branch_id' => Branch::factory(),
            'name' => $this->faker->word().' Terminal',
            'serial_number' => strtoupper($this->faker->unique()->bothify('###??####')),
            'ip_address' => $this->faker->ipv4,
            'port' => 4370,
            'comm_key' => 0,
            'timezone' => config('app.timezone'),
            'push_enabled' => true,
            'pull_enabled' => false,
            'is_active' => true,
        ];
    }
}
