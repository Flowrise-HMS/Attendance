<?php

namespace Modules\Attendance\Enums;

use Filament\Support\Contracts\HasLabel;
use Illuminate\Contracts\Support\Htmlable;

enum VerifyType: string implements HasLabel
{
    case Password = 'password';
    case Fingerprint = 'fingerprint';
    case Face = 'face';
    case Card = 'card';
    case Other = 'other';

    public function getLabel(): string|Htmlable|null
    {
        return match ($this) {
            self::Password => 'Password',
            self::Fingerprint => 'Fingerprint',
            self::Face => 'Face',
            self::Card => 'Card',
            self::Other => 'Other',
        };
    }

    public static function fromCode(?int $code): ?self
    {
        return match ($code) {
            0 => self::Password,
            1 => self::Fingerprint,
            2 => self::Card,
            3 => self::Face,
            default => $code === null ? null : self::Other,
        };
    }
}
