<?php

namespace App\Models;

use App\Enums\RequestSubStatusCode;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Query\Builder as QueryBuilder;

class RequestSubStatus extends Model
{
    use HasFactory;

    public $timestamps = false;

    protected $fillable = [
        'name',
        'code',
        'request_status_id',
        'description',
        'is_retired',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_retired' => 'boolean',
            'code' => RequestSubStatusCode::class,
        ];
    }

    /**
     * The assistance that belong to the RequestSubStatus
     */
    public function assistance(): BelongsToMany
    {
        return $this->belongsToMany(Assistance::class)->withTimestamps()->using(AssistanceRequestSubStatus::class);
    }

    public function requestStatus(): BelongsTo
    {
        return $this->belongsTo(RequestStatus::class);
    }

    /**
     * @param  Builder<RequestSubStatus>  $query
     * @return Builder<RequestSubStatus>
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_retired', false);
    }

    public function scopeLatestStatus($query)
    {
        $query->whereIn('assistance_request_sub_status.recorded_at', function (QueryBuilder $query) {
            $query->from('assistance_request_sub_status')
                ->selectRaw('max(`recorded_at`)')
                ->groupBy('assistance_request_sub_status.assistance_id');
        });
    }
}
