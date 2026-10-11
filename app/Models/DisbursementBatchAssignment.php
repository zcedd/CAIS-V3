<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DisbursementBatchAssignment extends Model
{
    protected $fillable = [
        'disbursement_batch_id',
        'user_id',
        'assignment_role',
        'assigned_by',
    ];

    public function batch(): BelongsTo
    {
        return $this->belongsTo(DisbursementBatch::class, 'disbursement_batch_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function assigner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_by');
    }
}
