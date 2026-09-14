<?php

namespace App\Models;

use Database\Factories\AssistanceAssignmentFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AssistanceAssignment extends Model
{
    /** @use HasFactory<AssistanceAssignmentFactory> */
    use HasFactory;

    protected $fillable = [
        'assistance_id',
        'assigned_from_id',
        'assigned_to_id',
        'assigned_by_id',
        'remark',
    ];

    public function assistance(): BelongsTo
    {
        return $this->belongsTo(Assistance::class);
    }

    public function assignedFrom(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_from_id');
    }

    public function assignedTo(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to_id');
    }

    public function assignedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_by_id');
    }
}
