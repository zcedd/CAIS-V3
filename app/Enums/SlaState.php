<?php

namespace App\Enums;

enum SlaState: string
{
    case OnTime = 'on_time';
    case DueSoon = 'due_soon';
    case Overdue = 'overdue';
    case Paused = 'paused';
    case None = 'none';

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
            self::OnTime => 'On time',
            self::DueSoon => 'Due soon',
            self::Overdue => 'Overdue',
            self::Paused => 'Paused',
            self::None => 'No SLA',
        };
    }
}
