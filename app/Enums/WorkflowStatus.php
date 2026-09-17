<?php

namespace App\Enums;

enum WorkflowStatus: string
{
    case Draft = 'draft';
    case Published = 'published';
    case Active = 'active';
    case Inactive = 'inactive';

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
            self::Draft => 'Draft',
            self::Published => 'Published',
            self::Active => 'Active',
            self::Inactive => 'Inactive',
        };
    }

    public function isMutable(): bool
    {
        return $this === self::Draft;
    }

    public function isAssignable(): bool
    {
        return $this === self::Active;
    }
}
