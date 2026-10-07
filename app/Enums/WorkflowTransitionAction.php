<?php

namespace App\Enums;

enum WorkflowTransitionAction: string
{
    case Advance = 'advance';
    case Complete = 'complete';
    case Return = 'return';
    case Reject = 'reject';
    case Hold = 'hold';
    case Close = 'close';
    case Skip = 'skip';

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
            self::Advance => 'Advance',
            self::Complete => 'Complete',
            self::Return => 'Return',
            self::Reject => 'Reject',
            self::Hold => 'Hold',
            self::Close => 'Close',
            self::Skip => 'Skip',
        };
    }

    public function resultingTaskStatus(): WorkflowTaskStatus
    {
        return match ($this) {
            self::Reject => WorkflowTaskStatus::Rejected,
            self::Return => WorkflowTaskStatus::Returned,
            default => WorkflowTaskStatus::Completed,
        };
    }
}
