<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Query\Builder;

class RequestSubStatus extends Model
{
    use HasFactory;

    /**
     * The assistance that belong to the RequestSubStatus
     */
    public function assistance(): BelongsToMany
    {
        return $this->belongsToMany(Assistance::class)->withTimestamps()->using(AssistanceRequestSubStatus::class);
    }

    /**
     * Get the status associated with the RequestSubStatus
     */
    public function requestStatus(): HasOne
    {
        return $this->hasOne(RequestStatus::class, 'id', 'request_status_id');
    }

    public function scopeLatestStatus($query)
    {
        $query->whereIn('assistance_request_sub_status.recorded_at', function (Builder $query) {
            $query->from('assistance_request_sub_status')
                ->selectRaw('max(`recorded_at`)')
                ->groupBy('assistance_request_sub_status.assistance_id');
        });
    }
}
