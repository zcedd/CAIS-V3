<?php

namespace App\Enums;

enum ProgramApprovalAction: string
{
    case Submit = 'submit';
    case Endorse = 'endorse';
    case Revise = 'revise';
    case Approve = 'approve';
    case Return = 'return';
}
