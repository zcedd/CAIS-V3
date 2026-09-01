<?php

namespace App\Support;

class DocumentRequirementMilestone
{
    public const Verified = 'verified';

    public const Delivered = 'delivered';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return [
            self::Verified,
            self::Delivered,
        ];
    }
}
