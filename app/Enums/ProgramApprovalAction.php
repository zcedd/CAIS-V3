<?php

namespace App\Enums;

enum ProgramApprovalAction: string
{
    case Submitted = 'submitted';
    case Endorsed = 'endorsed';
    case Approved = 'approved';
    case Returned = 'returned';
}
