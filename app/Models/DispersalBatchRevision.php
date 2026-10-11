<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DispersalBatchRevision extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = [
        'dispersal_batch_id',
        'revision',
        'snapshot',
        'submitted_by',
        'created_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'revision' => 'integer',
            'snapshot' => 'array',
            'created_at' => 'datetime',
        ];
    }

    public function batch(): BelongsTo
    {
        return $this->belongsTo(DispersalBatch::class, 'dispersal_batch_id');
    }

    public function submitter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'submitted_by');
    }
}
