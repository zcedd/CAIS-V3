<?php

namespace App\Support;

class IdentityNormalizer
{
    public static function name(?string $value): string
    {
        if ($value === null) {
            return '';
        }

        $collapsed = preg_replace('/\s+/u', ' ', trim($value)) ?? '';

        return mb_strtoupper($collapsed);
    }

    public static function identificationNumber(?string $value): string
    {
        if ($value === null) {
            return '';
        }

        $stripped = preg_replace('/[\s\-\.]/u', '', $value) ?? '';

        return mb_strtoupper($stripped);
    }

    public static function namesMatch(?string $left, ?string $right): bool
    {
        $normalizedLeft = self::name($left);
        $normalizedRight = self::name($right);

        return $normalizedLeft !== ''
            && $normalizedLeft === $normalizedRight;
    }
}
