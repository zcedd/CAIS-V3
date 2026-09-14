<?php

namespace App\Support;

class UnspscCodeLevel
{
    public const Segment = 'segment';

    public const Family = 'family';

    public const ItemClass = 'class';

    public const Commodity = 'commodity';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return [
            self::Segment,
            self::Family,
            self::ItemClass,
            self::Commodity,
        ];
    }
}
