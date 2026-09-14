<?php

namespace App\Enums;

enum StockMovementType: string
{
    case OpeningBalance = 'opening_balance';
    case Receipt = 'receipt';
    case AdjustmentIn = 'adjustment_in';
    case AdjustmentOut = 'adjustment_out';
    case Allocate = 'allocate';
    case Deallocate = 'deallocate';
    case Issue = 'issue';
    case Restore = 'restore';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    /**
     * @return list<self>
     */
    public static function receipts(): array
    {
        return [
            self::OpeningBalance,
            self::Receipt,
        ];
    }

    public function increasesOnHand(): bool
    {
        return match ($this) {
            self::OpeningBalance, self::Receipt, self::AdjustmentIn, self::Restore => true,
            default => false,
        };
    }
}
