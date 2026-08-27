<?php

namespace App\Support;

class StockMovementType
{
    public const OpeningBalance = 'opening_balance';

    public const Receipt = 'receipt';

    public const AdjustmentIn = 'adjustment_in';

    public const AdjustmentOut = 'adjustment_out';

    public const Allocate = 'allocate';

    public const Deallocate = 'deallocate';

    public const Issue = 'issue';

    public const Restore = 'restore';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return [
            self::OpeningBalance,
            self::Receipt,
            self::AdjustmentIn,
            self::AdjustmentOut,
            self::Allocate,
            self::Deallocate,
            self::Issue,
            self::Restore,
        ];
    }

    /**
     * @return list<string>
     */
    public static function receiptValues(): array
    {
        return [
            self::OpeningBalance,
            self::Receipt,
        ];
    }

    public static function increasesOnHand(string $type): bool
    {
        return in_array($type, [
            self::OpeningBalance,
            self::Receipt,
            self::AdjustmentIn,
            self::Restore,
        ], true);
    }
}
