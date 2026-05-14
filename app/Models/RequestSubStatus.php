<?php

namespace App\Models;

use Illuminate\Database\Query\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class RequestSubStatus extends Model
{
    use HasFactory;

    /**
     * The assistance that belong to the RequestSubStatus
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsToMany
     */
    public function assistance(): BelongsToMany
    {
        return $this->belongsToMany(Assistance::class)->withTimestamps()->using(AssistanceRequestSubStatus::class);
    }

    /**
     * Get the status associated with the RequestSubStatus
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasOne
     */
    public function requestStatus(): HasOne
    {
        return $this->hasOne(RequestStatus::class, 'id', 'request_status_id');
    }

    public function scopeLatestStatus($query)
    {
        $query->whereIn('assistance_request_sub_status.created_at', function (Builder $query) {
            $query->from('assistance_request_sub_status')
                ->selectRaw('max(`created_at`)')
                ->groupBy('assistance_request_sub_status.assistance_id');
        });
    }
}
