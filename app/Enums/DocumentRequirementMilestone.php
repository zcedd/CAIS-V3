<?php

namespace App\Enums;

enum DocumentRequirementMilestone: string
{
    case Verified = 'verified';
    case Delivered = 'delivered';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
