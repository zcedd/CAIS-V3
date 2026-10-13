<?php

namespace App\Enums;

enum ProgramDistributionMode: string
{
    case Individual = 'individual';
    case Batch = 'batch';
    case Both = 'both';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    public function label(): string
    {
        return match ($this) {
            self::Individual => 'Individual distribution',
            self::Batch => 'Batch distribution',
            self::Both => 'Individual and batch',
        };
    }

    public function allowsIndividual(): bool
    {
        return $this === self::Individual || $this === self::Both;
    }

    public function allowsBatch(): bool
    {
        return $this === self::Batch || $this === self::Both;
    }
}
