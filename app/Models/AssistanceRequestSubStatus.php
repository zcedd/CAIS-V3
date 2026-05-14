<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Pivot;

class AssistanceRequestSubStatus extends Pivot
{
    use HasFactory;

    public function assistance()
    {
        return $this->belongsTo(Assistance::class, 'assistance_id');
    }
}
