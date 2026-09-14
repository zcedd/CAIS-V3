<?php

namespace App\Enums;

enum ProgramKind: string
{
    case Standalone = 'standalone';
    case Scheme = 'scheme';
    case Batch = 'batch';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    /**
     * @return list<self>
     */
    public static function creatable(): array
    {
        return [
            self::Standalone,
            self::Scheme,
        ];
    }

    /**
     * @return list<self>
     */
    public static function encodable(): array
    {
        return [
            self::Standalone,
            self::Batch,
        ];
    }

    public function label(): string
    {
        return match ($this) {
            self::Standalone => 'One-off program',
            self::Scheme => 'Program with batches',
            self::Batch => 'Batch',
        };
    }

    public function isEncodable(): bool
    {
        return in_array($this, self::encodable(), true);
    }

    public function isScheme(): bool
    {
        return $this === self::Scheme;
    }

    public function isBatch(): bool
    {
        return $this === self::Batch;
    }

    public function isStandalone(): bool
    {
        return $this === self::Standalone;
    }
}
