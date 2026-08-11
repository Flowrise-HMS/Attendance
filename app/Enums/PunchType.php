<?php

namespace Modules\Attendance\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;
use Illuminate\Contracts\Support\Htmlable;

enum PunchType: string implements HasColor, HasLabel
{
    case In = 'in';
    case Out = 'out';
    case BreakIn = 'break_in';
    case BreakOut = 'break_out';
    case OvertimeIn = 'overtime_in';
    case OvertimeOut = 'overtime_out';
    case Unspecified = 'unspecified';

    public function getLabel(): string|Htmlable|null
    {
        return match ($this) {
            self::In => 'In',
            self::Out => 'Out',
            self::BreakIn => 'Break in',
            self::BreakOut => 'Break out',
            self::OvertimeIn => 'Overtime in',
            self::OvertimeOut => 'Overtime out',
            self::Unspecified => 'Unspecified',
        };
    }

    public function getColor(): string|array|null
    {
        return match ($this) {
            self::In => 'success',
            self::Out => 'danger',
            self::BreakIn, self::BreakOut => 'warning',
            self::OvertimeIn, self::OvertimeOut => 'info',
            self::Unspecified => 'gray',
        };
    }

    public static function fromStatus(int $status): self
    {
        return match ($status) {
            0 => self::In,
            1 => self::Out,
            4 => self::BreakIn,
            5 => self::BreakOut,
            14 => self::OvertimeIn,
            15 => self::OvertimeOut,
            default => self::Unspecified,
        };
    }
}
