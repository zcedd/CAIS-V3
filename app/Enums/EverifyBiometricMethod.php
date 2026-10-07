<?php

namespace App\Enums;

enum EverifyBiometricMethod: string
{
    case Face = 'face';
    case Fingerprint = 'fingerprint';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
