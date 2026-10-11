<?php

namespace App\Enums;

enum DispersalItemApprovalStatus: string
{
    case Pending = 'pending';
    case Approved = 'approved';
    case Returned = 'returned';
    case Excluded = 'excluded';

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
            self::Pending => 'Pending',
            self::Approved => 'Approved',
            self::Returned => 'Returned',
            self::Excluded => 'Excluded',
        };
    }
}
