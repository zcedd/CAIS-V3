<?php

namespace App\Enums;

enum ProgramApprovalAction: string
{
    case Submitted = 'submitted';
    case Approved = 'approved';
    case Returned = 'returned';
}
