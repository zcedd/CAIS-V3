<?php

namespace App\Enums;

enum WorkflowTaskStatus: string
{
    case Pending = 'pending';
    case Claimed = 'claimed';
    case InProgress = 'in_progress';
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
            self::Pending => 'Pending',
            self::Claimed => 'Claimed',
            self::InProgress => 'In Progress',
            self::Completed => 'Completed',
            self::Returned => 'Returned',
            self::Rejected => 'Rejected',
            self::Cancelled => 'Cancelled',
        };
    }

    public function isOpen(): bool
    {
        return in_array($this, [self::Pending, self::Claimed, self::InProgress], true);
    }

    public function isTerminal(): bool
    {
        return ! $this->isOpen();
    }
}
