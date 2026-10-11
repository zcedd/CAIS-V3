<?php

namespace App\Enums;

enum DisbursementReceiptStatus: string
{
    case Issued = 'issued';
    case Superseded = 'superseded';
    case Voided = 'voided';

    public function label(): string
    {
        return match ($this) {
            self::Issued => 'Issued',
            self::Superseded => 'Superseded',
            self::Voided => 'Voided',
        };
    }
}
