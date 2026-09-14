<?php

namespace App\Enums;

enum AssistanceItemOrigin: string
{
    case Requested = 'requested';
    case Additional = 'additional';
    case Substitute = 'substitute';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    /**
     * Origins that may only be created while releasing items, never on the request form.
     *
     * @return list<self>
     */
    public static function unrequested(): array
    {
        return [
            self::Additional,
            self::Substitute,
        ];
    }
}
