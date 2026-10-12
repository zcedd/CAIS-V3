<?php

namespace App\Models;

use App\Enums\DistributionEvidenceType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DistributionEvidence extends Model
{
    protected $table = 'distribution_evidence';

    protected $fillable = [
        'distribution_transaction_id',
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
            'evidence_type' => DistributionEvidenceType::class,
            'file_size' => 'integer',
            'captured_at' => 'datetime',
            'received_at' => 'datetime',
            'metadata' => 'array',
        ];
    }

    public function transaction(): BelongsTo
    {
        return $this->belongsTo(DistributionTransaction::class, 'distribution_transaction_id');
    }

    public function capturedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'captured_by');
    }
}
