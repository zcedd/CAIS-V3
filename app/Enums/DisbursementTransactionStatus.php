<?php

namespace App\Enums;

enum DisbursementTransactionStatus: string
{
    case Draft = 'draft';
    case Finalized = 'finalized';
    case Voided = 'voided';

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Draft',
            self::Finalized => 'Finalized',
            self::Voided => 'Voided',
        };
    }
}
