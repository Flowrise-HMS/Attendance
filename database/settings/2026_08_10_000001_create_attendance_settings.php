<?php

use Spatie\LaravelSettings\Migrations\SettingsMigration;

return new class extends SettingsMigration
{
    public function up(): void
    {
        $this->migrator->inGroup('attendance', function ($blueprint): void {
            $blueprint->add('default_late_grace_minutes', 15);
            $blueprint->add('default_overtime_after_minutes', 60);
            $blueprint->add('default_start_time', '08:00');
            $blueprint->add('default_end_time', '17:00');
            $blueprint->add('weekend_days', ['saturday', 'sunday']);
            $blueprint->add('pull_interval_minutes', 15);
            $blueprint->add('push_enabled', true);
        });
    }
};
