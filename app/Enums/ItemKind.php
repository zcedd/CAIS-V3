<?php

namespace App\Enums;

use App\Support\EmptyCell;

enum ItemKind: string
{
    case Goods = 'goods';
    case Cash = 'cash';
    case Service = 'service';

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
            self::Goods => 'Goods',
            self::Cash => 'Cash',
            self::Service => 'Service',
        };
    }

    public function tracksInventory(): bool
    {
        return $this === self::Goods;
    }

    public function isCash(): bool
    {
        return $this === self::Cash;
    }

    public static function formatQuantity(?int $quantity, ?string $unit, ?self $kind): string
    {
        if ($kind?->isCash() ?? false) {
            if ($quantity === null) {
                return '₱0.00';
            }

            return '₱'.number_format($quantity, 2);
        }

        if ($quantity === null) {
            return $unit ?? EmptyCell::VALUE;
        }

        return $unit ? "{$quantity} {$unit}" : (string) $quantity;
    }
}
