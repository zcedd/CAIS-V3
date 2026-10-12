<?php

namespace App\Enums;

enum DistributionDecisionAction: string
{
    case Approved = 'approved';
    case Returned = 'returned';
    case Rejected = 'rejected';
    case Excluded = 'excluded';
    case Modified = 'modified';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
