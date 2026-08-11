<?php

namespace Modules\Attendance\Settings;

use Spatie\LaravelSettings\Settings;

class AttendanceSettings extends Settings
{
    public int $default_late_grace_minutes = 15;

    public int $default_overtime_after_minutes = 60;

    public ?string $default_start_time = '08:00';

    public ?string $default_end_time = '17:00';

    public array $weekend_days = ['saturday', 'sunday'];

    public int $pull_interval_minutes = 15;

    public bool $push_enabled = true;

    public static function group(): string
    {
        return 'attendance';
    }
}
