<?php

namespace App\Enums;

enum ProgramApprovalStatus: string
{
    case Draft = 'draft';
    case AwaitingAdministrator = 'awaiting_administrator';
    case AwaitingGovernor = 'awaiting_governor';
    case Approved = 'approved';
    case Returned = 'returned';

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
            self::AwaitingAdministrator => 'Awaiting administrator',
            self::AwaitingGovernor => 'Awaiting executive',
            self::Approved => 'Approved',
            self::Returned => 'Returned',
        };
    }
}
