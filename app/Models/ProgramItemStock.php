<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProgramItemStock extends Model
{
    protected $fillable = [
        'program_id',
        'item_id',
        'remaining',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'remaining' => 'integer',
        ];
    }

    public function program(): BelongsTo
    {
        return $this->belongsTo(Program::class);
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class);
    }
}
