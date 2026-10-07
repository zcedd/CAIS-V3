<?php

namespace App\Enums;

enum ProgramApprovalStatus: string
{
    case Draft = 'draft';
    case Proposed = 'proposed';
    case AwaitingGovernor = 'awaiting_governor';
    case Approved = 'approved';
    case Returned = 'returned';

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Draft',
            self::Proposed => 'Proposed',
            self::AwaitingGovernor => 'Awaiting governor',
            self::Approved => 'Approved',
            self::Returned => 'Returned',
        };
    }

    public function canReviseDefinition(): bool
    {
        return $this === self::Draft || $this === self::Returned;
    }
}
