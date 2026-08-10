<?php

namespace App\Models;

use Database\Factories\ProgramFieldFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class ProgramField extends Model
{
    /** @use HasFactory<ProgramFieldFactory> */
    use HasFactory;

    use SoftDeletes;

    protected $fillable = [
        'program_id',
        'label',
        'key',
        'type',
        'options',
        'is_required',
        'show_in_table',
        'sort_order',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'options' => 'array',
            'is_required' => 'boolean',
            'show_in_table' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    public function program(): BelongsTo
    {
        return $this->belongsTo(Program::class);
    }

    public function values(): HasMany
    {
        return $this->hasMany(AssistanceFieldValue::class);
    }
}
