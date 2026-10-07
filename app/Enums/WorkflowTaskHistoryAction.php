<?php

namespace App\Enums;

enum WorkflowTaskHistoryAction: string
{
    case Created = 'created';
    case Assigned = 'assigned';
    case Reassigned = 'reassigned';
    case Claimed = 'claimed';
    case Started = 'started';
    case Completed = 'completed';
    case Returned = 'returned';
    case Rejected = 'rejected';
    case Cancelled = 'cancelled';

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
            self::Created => 'Created',
            self::Assigned => 'Assigned',
            self::Reassigned => 'Reassigned',
            self::Claimed => 'Claimed',
            self::Started => 'Started',
            self::Completed => 'Completed',
            self::Returned => 'Returned',
            self::Rejected => 'Rejected',
            self::Cancelled => 'Cancelled',
        };
    }
}
