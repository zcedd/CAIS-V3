<?php

namespace App\Models;

use DDZobov\PivotSoftDeletes\Relations\Pivot;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AssistanceRequestSubStatus extends Pivot
{
    use HasFactory;

    public function assistance()
    {
        return $this->belongsTo(Assistance::class, 'assistance_id');
    }
}
