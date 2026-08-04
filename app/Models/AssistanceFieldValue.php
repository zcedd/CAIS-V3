<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AssistanceFieldValue extends Model
{
    protected $fillable = [
        'assistance_id',
        'program_field_id',
        'value',
    ];

    public function assistance(): BelongsTo
    {
        return $this->belongsTo(Assistance::class);
    }

    public function programField(): BelongsTo
    {
        return $this->belongsTo(ProgramField::class);
    }
}
