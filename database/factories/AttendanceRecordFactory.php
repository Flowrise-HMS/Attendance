<?php

namespace Modules\Attendance\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Attendance\Enums\PunchSource;
use Modules\Attendance\Enums\PunchType;
use Modules\Attendance\Models\AttendanceMachine;
use Modules\Attendance\Models\AttendanceRecord;

/** @extends Factory<AttendanceRecord> */
class AttendanceRecordFactory extends Factory
{
    protected $model = AttendanceRecord::class;

    public function definition(): array
    {
        $branchId = AttendanceMachine::factory()->create()->branch_id;
        $badge = (string) $this->faker->unique()->numberBetween(1000, 9999);
        $punchedAt = $this->faker->dateTimeThisMonth();

        return [
            'branch_id' => $branchId,
            'badge_number' => $badge,
            'punched_at' => $punchedAt,
            'source' => PunchSource::Push,
            'punch_type' => PunchType::In,
            'dedup_hash' => AttendanceRecord::makeDedupHash('unassigned', $badge, $punchedAt->format('Y-m-d H:i:s')),
        ];
    }

    public function forMachine(AttendanceMachine $machine, string $badge, \DateTimeInterface $punchedAt): static
    {
        return $this->state(fn (): array => [
            'branch_id' => $machine->branch_id,
            'machine_id' => $machine->id,
            'badge_number' => $badge,
            'punched_at' => $punchedAt,
            'dedup_hash' => AttendanceRecord::makeDedupHash($machine->id, $badge, $punchedAt->format('Y-m-d H:i:s')),
        ]);
    }
}
