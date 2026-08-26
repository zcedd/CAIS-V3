<?php

namespace App\Models;

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
    ];

    public function requirements(): HasMany
    {
        return $this->hasMany(ProgramDocumentRequirement::class);
    }

    public function documents(): HasMany
    {
        return $this->hasMany(AssistanceDocument::class);
    }
}
