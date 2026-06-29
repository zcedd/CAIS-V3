<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class AssistanceItem extends Model
{
    use HasFactory;
    use LogsActivity;
    use SoftDeletes;

    protected $table = 'assistance_item';

    protected $fillable = ['assistance_id', 'item_id', 'is_received', 'quantity', 'specification'];

    public $incrementing = true;

    public function assistance(): BelongsTo
    {
        return $this->belongsTo(Assistance::class, 'assistance_id');
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class, 'item_id');
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logFillable()
            ->useLogName('AssistanceItem')
            ->setDescriptionForEvent(fn (string $eventName) => "This AssistanceItem model has been {$eventName}")
            ->dontSubmitEmptyLogs();
    }
}
