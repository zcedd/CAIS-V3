<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DispersalBatchDocument extends Model
{
    protected $fillable = [
        'dispersal_batch_id',
        'document_type_id',
        'uploaded_by',
        'original_name',
        'disk',
        'path',
        'mime_type',
        'size',
        'notes',
    ];

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'disk' => 'local',
    ];

    public function batch(): BelongsTo
    {
        return $this->belongsTo(DispersalBatch::class, 'dispersal_batch_id');
    }

    public function documentType(): BelongsTo
    {
        return $this->belongsTo(DocumentType::class);
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }
}
