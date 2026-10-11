<?php

namespace App\Models;

use App\Enums\DisbursementAcknowledgmentMode;
use App\Enums\DisbursementRecipientType;
use App\Enums\DisbursementTransactionStatus;
use App\Enums\DisbursementVerificationMethod;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class DisbursementTransaction extends Model
{
    protected $fillable = [
        'disbursement_batch_id',
        'disbursement_batch_item_id',
        'transaction_number',
        'idempotency_key',
        'received_by_type',
        'received_by_name',
        'representative_relationship',
        'representative_verification',
        'verification_method',
        'verification_status',
        'acknowledgment_mode',
        'alternative_reason',
        'privacy_acknowledged_at',
        'camera_waiver_reason',
        'camera_waived_by',
        'status',
        'notes',
        'released_by',
        'witnessed_by',
        'captured_at',
        'finalized_at',
        'voided_at',
        'void_reason',
        'created_by',
        'updated_by',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'received_by_type' => DisbursementRecipientType::class,
            'verification_method' => DisbursementVerificationMethod::class,
            'acknowledgment_mode' => DisbursementAcknowledgmentMode::class,
            'status' => DisbursementTransactionStatus::class,
            'privacy_acknowledged_at' => 'datetime',
            'captured_at' => 'datetime',
            'finalized_at' => 'datetime',
            'voided_at' => 'datetime',
        ];
    }

    public function batch(): BelongsTo
    {
        return $this->belongsTo(DisbursementBatch::class, 'disbursement_batch_id');
    }

    public function batchItem(): BelongsTo
    {
        return $this->belongsTo(DisbursementBatchItem::class, 'disbursement_batch_item_id');
    }

    public function evidence(): HasMany
    {
        return $this->hasMany(DisbursementEvidence::class);
    }

    public function receipts(): HasMany
    {
        return $this->hasMany(DisbursementAcknowledgmentReceipt::class);
    }

    public function currentReceipt(): HasOne
    {
        return $this->hasOne(DisbursementAcknowledgmentReceipt::class)->latestOfMany();
    }

    public function releases(): HasMany
    {
        return $this->hasMany(DisbursementReleaseRecord::class);
    }

    public function releasedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'released_by');
    }

    public function witnessedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'witnessed_by');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function cameraWaivedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'camera_waived_by');
    }
}
