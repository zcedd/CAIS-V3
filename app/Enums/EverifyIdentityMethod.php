<?php

namespace App\Enums;

enum EverifyIdentityMethod: string
{
    case Query = 'query';
    case Qr = 'qr';
    case Pcn = 'pcn';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    public function usesQrValue(): bool
    {
        return $this !== self::Query;
    }
}
