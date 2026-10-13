<?php

namespace App\Enums;

enum DistributionType: string
{
    case Individual = 'individual';
    case Batch = 'batch';

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
            self::Individual => 'Individual',
            self::Batch => 'Batch',
        };
    }

    public function approvalLabel(): string
    {
        return match ($this) {
            self::Individual => 'Direct release. Separate executive batch approval is not required.',
            self::Batch => 'Executive approval is required before release processing.',
        };
    }
}
