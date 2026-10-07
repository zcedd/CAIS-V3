<?php

namespace App\Enums;

enum EverifyIntakeMethod: string
{
    case Manual = 'manual';
    case Everify = 'everify';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
