<?php

namespace Modules\Attendance\Enums;

use Filament\Support\Contracts\HasLabel;
use Illuminate\Contracts\Support\Htmlable;

enum PunchSource: string implements HasLabel
{
    case Push = 'push';
    case Pull = 'pull';
    case Import = 'import';
    case Manual = 'manual';

    public function getLabel(): string|Htmlable|null
    {
        return match ($this) {
            self::Push => 'Push',
            self::Pull => 'Pull',
            self::Import => 'Import',
            self::Manual => 'Manual',
        };
    }
}
