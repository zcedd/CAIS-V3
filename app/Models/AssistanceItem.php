<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
// use Illuminate\Database\Eloquent\Model;
// use Illuminate\Database\Eloquent\Relations\Pivot;
use Spatie\Activitylog\Traits\LogsActivity;
use Spatie\Activitylog\LogOptions;
use DDZobov\PivotSoftDeletes\Relations\Pivot;

class AssistanceItem extends Pivot
{
    use HasFactory;
    use LogsActivity;

    protected $fillable = ['assistance_id', 'item_id', 'is_received', 'specification'];
    public $incrementing = true;
    
    public function assistance()
    {
        return $this->belongsTo(Assistance::class, 'assitance_id');
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
        ->logFillable()
        ->useLogName('AssistanceItem')
        ->setDescriptionForEvent(fn(string $eventName) => "This AssistanceItem model has been {$eventName}")
        ->dontSubmitEmptyLogs();
    }
}
