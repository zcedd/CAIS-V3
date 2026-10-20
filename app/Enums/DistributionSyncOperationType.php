<?php

namespace App\Enums;

enum DistributionSyncOperationType: string
{
    case Release = 'release';
    case Absent = 'absent';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
