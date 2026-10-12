<?php

namespace App\Models;

use App\Enums\DistributionReceiptStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DistributionAcknowledgmentReceipt extends Model
{
    protected $fillable = [
        'distribution_transaction_id',
        'receipt_number',
        'verification_token',
        'issued_at',
        'issued_by',
        'version',
        'supersedes_receipt_id',
        'status',
        'reprint_count',
        'notes',
        'snapshot',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'issued_at' => 'datetime',
            'version' => 'integer',
            'status' => DistributionReceiptStatus::class,
            'reprint_count' => 'integer',
            'snapshot' => 'array',
        ];
    }

    public function transaction(): BelongsTo
    {
        return $this->belongsTo(DistributionTransaction::class, 'distribution_transaction_id');
    }

    public function issuer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'issued_by');
    }

    public function supersededReceipt(): BelongsTo
    {
        return $this->belongsTo(self::class, 'supersedes_receipt_id');
    }
}
