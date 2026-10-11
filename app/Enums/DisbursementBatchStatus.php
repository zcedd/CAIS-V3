<?php

namespace App\Enums;

enum DisbursementBatchStatus: string
{
    case Draft = 'draft';
    case ForValidation = 'for_validation';
    case ForApproval = 'for_approval';
    case Returned = 'returned';
    case Rejected = 'rejected';
    case Approved = 'approved';
    case Scheduled = 'scheduled';
    case InProgress = 'in_progress';
    case Completed = 'completed';
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
            self::Draft => 'Draft',
            self::ForValidation => 'For validation',
            self::ForApproval => 'For approval',
            self::Returned => 'Returned',
            self::Rejected => 'Rejected',
            self::Approved => 'Approved',
            self::Scheduled => 'Scheduled',
            self::InProgress => 'In progress',
            self::Completed => 'Completed',
            self::Cancelled => 'Cancelled',
        };
    }

    public function canTransitionTo(self $to): bool
    {
        return in_array($to, $this->allowedTransitions(), true);
    }

    /**
     * @return list<self>
     */
    public function allowedTransitions(): array
    {
        return match ($this) {
            self::Draft => [self::ForValidation, self::Cancelled],
            self::ForValidation => [self::ForApproval, self::Draft, self::Cancelled],
            self::ForApproval => [self::Returned, self::Rejected, self::Approved, self::Cancelled],
            self::Returned => [self::ForValidation, self::Cancelled],
            self::Rejected => [self::Draft],
            self::Approved => [self::Scheduled, self::ForApproval, self::Cancelled],
            self::Scheduled => [self::InProgress, self::ForApproval, self::Cancelled],
            self::InProgress => [self::Completed],
            self::Completed, self::Cancelled => [],
        };
    }

    public function isEditable(): bool
    {
        return $this === self::Draft || $this === self::Returned;
    }

    /**
     * Statuses that keep an assistance request reserved for this batch.
     *
     * @return list<string>
     */
    public static function blockingValues(): array
    {
        return [
            self::Draft->value,
            self::ForValidation->value,
            self::ForApproval->value,
            self::Returned->value,
            self::Approved->value,
            self::Scheduled->value,
            self::InProgress->value,
        ];
    }
}
