<?php

namespace App\Models;

use App\Support\RequestStatusCode;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class RequestStatus extends Model
{
    use HasFactory;

    public $timestamps = false;

    protected $fillable = [
        'name',
        'code',
        'sort_order',
        'is_terminal',
        'is_hold',
        'pauses_sla',
        'is_retired',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'sort_order' => 'integer',
            'is_terminal' => 'boolean',
            'is_hold' => 'boolean',
            'pauses_sla' => 'boolean',
            'is_retired' => 'boolean',
            'code' => RequestStatusCode::class,
        ];
    }

    /**
     * Get all of the subStatus for the RequestStatus
     */
    public function subStatus(): HasMany
    {
        return $this->hasMany(RequestSubStatus::class);
    }

    /**
     * @param  Builder<RequestStatus>  $query
     * @return Builder<RequestStatus>
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_retired', false);
    }
}
