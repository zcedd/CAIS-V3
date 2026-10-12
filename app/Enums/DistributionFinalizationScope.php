<?php

namespace App\Enums;

enum DistributionFinalizationScope: string
{
    case Complete = 'complete';
    case Partial = 'partial';

    public function label(): string
    {
        return match ($this) {
            self::Complete => 'Fully approved',
            self::Partial => 'Partially finalized',
        };
    }
}
