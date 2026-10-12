<?php

namespace App\Enums;

enum DistributionEvidenceType: string
{
    case LivePhoto = 'live_photo';
    case Signature = 'signature';

    public function label(): string
    {
        return match ($this) {
            self::LivePhoto => 'Live photo',
            self::Signature => 'Signature',
        };
    }
}
