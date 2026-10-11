<?php

namespace App\Enums;

enum DisbursementApprovalStrategy: string
{
    case Sequential = 'sequential';
    case Parallel = 'parallel';

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
            self::Sequential => 'Sequential',
            self::Parallel => 'Parallel',
        };
    }
}
