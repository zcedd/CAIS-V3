<?php

namespace App\Models;

use App\Enums\DocumentTypeSlug;
use Database\Factories\DocumentTypeFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class DocumentType extends Model
{
    /** @use HasFactory<DocumentTypeFactory> */
    use HasFactory;

    protected $fillable = [
        'name',
        'slug',
        'is_active',
    ];

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'is_active' => true,
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    public function isProtected(): bool
    {
        return in_array($this->slug, DocumentTypeSlug::values(), true);
    }

    public function requirements(): HasMany
    {
        return $this->hasMany(ProgramDocumentRequirement::class);
    }

    public function documents(): HasMany
    {
        return $this->hasMany(AssistanceDocument::class);
    }
}
