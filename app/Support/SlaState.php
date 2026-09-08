<?php

namespace App\Support;

class SlaState
{
    public const OnTime = 'on_time';

    public const DueSoon = 'due_soon';

    public const Overdue = 'overdue';

    public const Paused = 'paused';

    public const None = 'none';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return [
            self::OnTime,
            self::DueSoon,
            self::Overdue,
            self::Paused,
            self::None,
        ];
    }

    public static function label(string $state): string
    {
        return match ($state) {
            self::OnTime => 'On time',
            self::DueSoon => 'Due soon',
            self::Overdue => 'Overdue',
            self::Paused => 'Paused',
            default => 'No SLA',
        };
    }
}
