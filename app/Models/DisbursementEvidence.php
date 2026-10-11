<?php

namespace App\Models;

use App\Enums\DisbursementEvidenceType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DisbursementEvidence extends Model
{
    protected $table = 'disbursement_evidence';

    protected $fillable = [
        'disbursement_transaction_id',
        'evidence_type',
        'disk',
        'path',
        'mime_type',
        'file_size',
        'sha256_hash',
        'captured_at',
        'received_at',
        'captured_by',
        'metadata',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'evidence_type' => DisbursementEvidenceType::class,
            'file_size' => 'integer',
            'captured_at' => 'datetime',
            'received_at' => 'datetime',
            'metadata' => 'array',
        ];
    }

    public function transaction(): BelongsTo
    {
        return $this->belongsTo(DisbursementTransaction::class, 'disbursement_transaction_id');
    }

    public function capturedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'captured_by');
    }
}
