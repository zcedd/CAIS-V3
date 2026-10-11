<?php

namespace App\Models;

use App\Enums\DispersalItemApprovalStatus;
use Database\Factories\DispersalBatchItemFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class DispersalBatchItem extends Model
{
    /** @use HasFactory<DispersalBatchItemFactory> */
    use HasFactory;

    protected $fillable = [
        'dispersal_batch_id',
        'assistance_id',
        'beneficiary_id',
        'approval_status',
        'reviewed_by',
        'reviewed_at',
        'review_remarks',
        'exclusion_reason',
        'warnings',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'approval_status' => DispersalItemApprovalStatus::class,
            'reviewed_at' => 'datetime',
            'warnings' => 'array',
        ];
    }

    public function batch(): BelongsTo
    {
        return $this->belongsTo(DispersalBatch::class, 'dispersal_batch_id');
    }

    public function assistance(): BelongsTo
    {
        return $this->belongsTo(Assistance::class);
    }

    public function beneficiary(): BelongsTo
    {
        return $this->belongsTo(Beneficiary::class);
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function lines(): HasMany
    {
        return $this->hasMany(DispersalBatchItemLine::class);
    }

    public function releases(): HasMany
    {
        return $this->hasMany(DispersalReleaseRecord::class);
    }
}
