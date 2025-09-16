<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class RequestStatus extends Model
{
    use HasFactory;

    /**
     * Get all of the subStatus for the RequestStatus
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function subStatus(): HasMany
    {
        return $this->hasMany(RequestSubStatus::class);
    }
}
