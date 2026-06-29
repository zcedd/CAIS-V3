<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class Item extends Model
{
    use HasFactory;
    use LogsActivity;
    use SoftDeletes;

    protected $fillable = ['name', 'department_id', 'item_unit_measurement_id'];

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logFillable()
            ->useLogName('Item')
            ->setDescriptionForEvent(fn (string $eventName) => "This Item model has been {$eventName}")
            ->dontSubmitEmptyLogs();
    }

    public function assistance()
    {
        return $this->belongsToMany(Assistance::class)->withSoftDeletes()->using(AssistanceItem::class);
    }

    /**
     * The project that belong to the Item
     *
     * @return BelongsToMany
     */
    public function project()
    {
        return $this->belongsToMany(Project::class)->using(ItemProject::class);
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class, 'department_id', 'id');
    }

    public function unitMeasurement(): BelongsTo
    {
        return $this->belongsTo(ItemUnitMeasurement::class, 'item_unit_measurement_id');
    }
}
