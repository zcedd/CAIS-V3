<?php

namespace App\Enums;

enum EverifyVerificationStatus: string
{
    case Verified = 'verified';
    case Skipped = 'skipped';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    public function label(): string
    {
        return match ($this) {
            self::Verified => 'PhilSys verified',
            self::Skipped => 'Manual entry',
        };
    }
}
