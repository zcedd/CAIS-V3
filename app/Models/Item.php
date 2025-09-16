<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Spatie\Activitylog\LogOptions;
// use DDZobov\PivotSoftDeletes\Model;
use Spatie\Activitylog\Traits\LogsActivity;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Item extends Model
{
    use HasFactory;
    use SoftDeletes;
    use LogsActivity;

    protected $fillable = ['name', 'department_id'];

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logFillable()
            ->useLogName('Item')
            ->setDescriptionForEvent(fn(string $eventName) => "This Item model has been {$eventName}")
            ->dontSubmitEmptyLogs();
    }

    public function assistance()
    {
        return $this->belongsToMany(Assistance::class)->withSoftDeletes()->using(AssistanceItem::class);
    }

    /**
     * The project that belong to the Item
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsToMany
     */
    public function project()
    {
        return $this->belongsToMany(Project::class)->using(ItemProject::class);
    }

    public function department()
    {
        return $this->belongsTo(Department::class, 'department_id', 'id');
    }
}
