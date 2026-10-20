<?php

namespace App\Models;

use App\Enums\DistributionSyncOperationStatus;
use App\Enums\DistributionSyncOperationType;
use Database\Factories\DistributionSyncOperationFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DistributionSyncOperation extends Model
{
    /** @use HasFactory<DistributionSyncOperationFactory> */
    use HasFactory;

    protected $fillable = [
        'client_operation_id',
        'user_id',
        'distribution_batch_id',
        'distribution_batch_item_id',
        'distribution_transaction_id',
        'type',
        'status',
        'device_captured_at',
        'result',
        'error',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => DistributionSyncOperationType::class,
            'status' => DistributionSyncOperationStatus::class,
            'device_captured_at' => 'datetime',
            'result' => 'array',
            'error' => 'array',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function batch(): BelongsTo
    {
        return $this->belongsTo(DistributionBatch::class, 'distribution_batch_id');
    }

    public function batchItem(): BelongsTo
    {
        return $this->belongsTo(DistributionBatchItem::class, 'distribution_batch_item_id');
    }

    public function transaction(): BelongsTo
    {
        return $this->belongsTo(DistributionTransaction::class, 'distribution_transaction_id');
    }
}
