<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EverifyLog extends Model
{
    protected $fillable = [
        'user_id',
        'individual_id',
        'purpose',
        'method',
        'url',
        'request',
        'response',
        'status_code',
        'query_log_id',
        'duration_ms',
    ];

    /**
     * @var list<string>
     */
    protected $hidden = [
        'request',
        'response',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'request' => 'encrypted:array',
            'response' => 'encrypted:array',
            'status_code' => 'integer',
            'duration_ms' => 'decimal:2',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function individual(): BelongsTo
    {
        return $this->belongsTo(Individual::class);
    }
}
