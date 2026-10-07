<?php

namespace App\Models;

use App\Enums\ProgramApprovalAction;
use App\Enums\ProgramApprovalStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProgramApprovalEvent extends Model
{
    protected $fillable = [
        'program_id',
        'user_id',
        'action',
        'from_status',
        'to_status',
        'comment',
    ];

    protected $casts = [
        'action' => ProgramApprovalAction::class,
        'from_status' => ProgramApprovalStatus::class,
        'to_status' => ProgramApprovalStatus::class,
    ];

    public function program(): BelongsTo
    {
        return $this->belongsTo(Program::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
