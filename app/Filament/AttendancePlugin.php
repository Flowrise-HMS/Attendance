<?php

namespace Modules\Attendance\Filament;

use Coolsam\Modules\Concerns\ModuleFilamentPlugin;
use Filament\Contracts\Plugin;
use Filament\Panel;

class AttendancePlugin implements Plugin
{
    use ModuleFilamentPlugin;

    public function getModuleName(): string
    {
        return 'Attendance';
    }

    public function getId(): string
    {
        return 'attendance';
    }

    public function boot(Panel $panel): void
    {
        //
    }
}
