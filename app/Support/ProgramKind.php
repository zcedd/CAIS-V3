<?php

namespace App\Support;

class ProgramKind
{
    public const Standalone = 'standalone';

    public const Scheme = 'scheme';

    public const Batch = 'batch';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return [
            self::Standalone,
            self::Scheme,
            self::Batch,
        ];
    }

    /**
     * @return list<string>
     */
    public static function creatableValues(): array
    {
        return [
            self::Standalone,
            self::Scheme,
        ];
    }

    /**
     * @return list<string>
     */
    public static function encodableValues(): array
    {
        return [
            self::Standalone,
            self::Batch,
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function labels(): array
    {
        return [
            self::Standalone => 'One-off program',
            self::Scheme => 'Program with batches',
            self::Batch => 'Batch',
        ];
    }

    public static function isEncodable(?string $kind): bool
    {
        return in_array($kind ?? self::Standalone, self::encodableValues(), true);
    }

    public static function isScheme(?string $kind): bool
    {
        return $kind === self::Scheme;
    }

    public static function isBatch(?string $kind): bool
    {
        return $kind === self::Batch;
    }

    public static function isStandalone(?string $kind): bool
    {
        return ($kind ?? self::Standalone) === self::Standalone;
    }
}
