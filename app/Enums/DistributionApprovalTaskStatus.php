<?php

namespace App\Enums;

enum DistributionApprovalTaskStatus: string
{
    case Pending = 'pending';
    case Waiting = 'waiting';
    case Completed = 'completed';
    case Cancelled = 'cancelled';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
