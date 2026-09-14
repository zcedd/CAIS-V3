<?php

namespace App\Support;

class AssistanceItemOrigin
{
    public const Requested = 'requested';

    public const Additional = 'additional';

    public const Substitute = 'substitute';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return [
            self::Requested,
            self::Additional,
            self::Substitute,
        ];
    }

    /**
     * Origins that may only be created while releasing items, never on the request form.
     *
     * @return list<string>
     */
    public static function unrequestedValues(): array
    {
        return [
            self::Additional,
            self::Substitute,
        ];
    }
}
