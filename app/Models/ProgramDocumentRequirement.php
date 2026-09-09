<?php

namespace App\Models;

use App\Enums\DocumentRequirementMilestone;
use Database\Factories\ProgramDocumentRequirementFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProgramDocumentRequirement extends Model
{
    /** @use HasFactory<ProgramDocumentRequirementFactory> */
    use HasFactory;

    protected $fillable = [
        'program_id',
        'document_type_id',
        'is_required',
        'required_before',
        'sort_order',
    ];

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'is_required' => true,
        'sort_order' => 0,
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'document_type_id' => 'integer',
            'is_required' => 'boolean',
            'sort_order' => 'integer',
            'required_before' => DocumentRequirementMilestone::class,
        ];
    }

    public function program(): BelongsTo
    {
        return $this->belongsTo(Program::class);
    }

    public function documentType(): BelongsTo
    {
        return $this->belongsTo(DocumentType::class);
    }
}
