<?php

namespace App\Enums;

enum DisbursementApprovalMode: string
{
    case Batch = 'batch';
    case Beneficiary = 'beneficiary';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    public function label(): string
    {
        return match ($this) {
            self::Batch => 'Whole batch',
            self::Beneficiary => 'Individual beneficiary',
        };
    }
}
