<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

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

    public function assistance(): BelongsToMany
    {
        return $this->belongsToMany(AssistanceRequest::class)->withSoftDeletes()->using(AssistanceRequestItem::class);
    }

    /**
     * The project that belong to the Item
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsToMany
     */
    public function project(): BelongsToMany
    {
        return $this->belongsToMany(Program::class)->using(ProgramItem::class);
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }
}
