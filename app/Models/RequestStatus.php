<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class RequestStatus extends Model
{
    use HasFactory;

    public $timestamps = false;

    protected $fillable = ['name'];

    /**
     * Get all of the subStatus for the RequestStatus
     */
    public function subStatus(): HasMany
    {
        return $this->hasMany(RequestSubStatus::class);
    }
}
