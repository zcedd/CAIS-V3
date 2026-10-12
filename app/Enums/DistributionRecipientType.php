<?php

namespace App\Enums;

enum DistributionRecipientType: string
{
    case Beneficiary = 'beneficiary';
    case Representative = 'representative';

    public function label(): string
    {
        return match ($this) {
            self::Beneficiary => 'Beneficiary',
            self::Representative => 'Authorized representative',
        };
    }
}
