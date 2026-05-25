<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\Pivot;

class AssistanceRequestSubStatus extends Pivot
{
    use HasFactory;

    protected $table = 'assistance_request_sub_status';

    public function assistance(): BelongsTo
    {
        return $this->belongsTo(Assistance::class, 'assistance_id');
    }

    public function requestSubStatus(): BelongsTo
    {
        return $this->belongsTo(RequestSubStatus::class);
    }
}
