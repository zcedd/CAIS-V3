<?php

namespace App\Enums;

enum ProgramApprovalAction: string
{
    case Submitted = 'submitted';
    case Endorsed = 'endorsed';
    case Approved = 'approved';
    case Returned = 'returned';

    public function trailLabel(): string
    {
        return match ($this) {
            self::Submitted => 'Department endorsement',
            self::Endorsed => 'Administrator endorsement',
            self::Approved => 'Governor sign-off',
            self::Returned => 'Returned',
        };
    }

    public function trailDoneLabel(): string
    {
        return match ($this) {
            self::Submitted => 'Signed & endorsed',
            self::Endorsed => 'Endorsed',
            self::Approved => 'Governor approved',
            self::Returned => 'Returned',
        };
    }
}
