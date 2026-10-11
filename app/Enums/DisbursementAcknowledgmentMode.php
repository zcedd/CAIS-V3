<?php

namespace App\Enums;

enum DisbursementAcknowledgmentMode: string
{
    case Signature = 'signature';
    case Alternative = 'alternative';

    public function label(): string
    {
        return match ($this) {
            self::Signature => 'Digital signature',
            self::Alternative => 'Alternative acknowledgment',
        };
    }
}
