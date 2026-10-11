<?php

namespace App\Enums;

enum DisbursementReleaseOutcome: string
{
    case Served = 'served';
    case Partial = 'partial';
    case Absent = 'absent';

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
            self::Served => 'Served',
            self::Partial => 'Partially served',
            self::Absent => 'Absent',
        };
    }
}
