<?php

namespace App\Enums;

enum DistributionSyncOperationStatus: string
{
    case Applied = 'applied';
    case Conflict = 'conflict';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
