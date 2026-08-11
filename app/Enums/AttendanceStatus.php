<?php

namespace Modules\Attendance\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;
use Illuminate\Contracts\Support\Htmlable;

enum AttendanceStatus: string implements HasColor, HasLabel
{
    case Present = 'present';
    case Late = 'late';
    case Absent = 'absent';
    case OnLeave = 'on_leave';
    case Weekend = 'weekend';
    case Holiday = 'holiday';
    case NoShift = 'no_shift';
    case NoPunch = 'no_punch';

    public function getLabel(): string|Htmlable|null
    {
        return match ($this) {
            self::Present => 'Present',
            self::Late => 'Late',
            self::Absent => 'Absent',
            self::OnLeave => 'On leave',
            self::Weekend => 'Weekend',
            self::Holiday => 'Holiday',
            self::NoShift => 'No shift',
            self::NoPunch => 'No punch',
        };
    }

    public function getColor(): string|array|null
    {
        return match ($this) {
            self::Present => 'success',
            self::Late => 'warning',
            self::Absent => 'danger',
            self::OnLeave => 'info',
            self::Weekend => 'gray',
            self::Holiday => 'info',
            self::NoShift => 'gray',
            self::NoPunch => 'warning',
        };
    }
}
