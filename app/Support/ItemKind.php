<?php

namespace App\Support;

class ItemKind
{
    public const Goods = 'goods';

    public const Cash = 'cash';

    public const Service = 'service';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return [
            self::Goods,
            self::Cash,
            self::Service,
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function labels(): array
    {
        return [
            self::Goods => 'Goods',
            self::Cash => 'Cash',
            self::Service => 'Service',
        ];
    }

    public static function tracksInventory(?string $kind): bool
    {
        return ($kind ?? self::Goods) === self::Goods;
    }

    public static function isCash(?string $kind): bool
    {
        return $kind === self::Cash;
    }

    public static function formatQuantity(?int $quantity, ?string $unit, ?string $kind): string
    {
        if (self::isCash($kind)) {
            if ($quantity === null) {
                return '₱0.00';
            }

            return '₱'.number_format($quantity, 2);
        }

        if ($quantity === null) {
            return $unit ?? '—';
        }

        return $unit ? "{$quantity} {$unit}" : (string) $quantity;
    }
}
