<?php

namespace App\Enums;

enum UnspscCodeLevel: string
{
    case Segment = 'segment';
    case Family = 'family';
    case ItemClass = 'class';
    case Commodity = 'commodity';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
