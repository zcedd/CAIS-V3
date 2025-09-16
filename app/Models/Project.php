<?php

namespace App\Models;

use Spatie\Activitylog\LogOptions;
use Illuminate\Database\Eloquent\Model;
use Spatie\Activitylog\Traits\LogsActivity;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Project extends Model
{
    use HasFactory;
    use SoftDeletes;
    use LogsActivity;

    protected $fillable = ['name', 'descriptions', 'source_of_fund', 'dateStarted', 'dateEnded', 'department_id', 'is_closed', 'created_at', 'updated_at'];

    protected $casts = [
        'dateStarted' => 'datetime:M d, Y',
        'dateEnded' => 'datetime:M d, Y',
    ];

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logFillable()
            ->useLogName('Project')
            ->setDescriptionForEvent(fn(string $eventName) => "This Project model has been {$eventName}")
            ->dontSubmitEmptyLogs();
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class, 'department_id', 'id');
    }

    public function assistance(): HasMany
    {
        return $this->hasMany(Assistance::class);
    }

    public function sourceOfFund(): BelongsToMany
    {
        return $this->belongsToMany(SourceOfFund::class);
    }

    public function item(): BelongsToMany
    {
        return $this->belongsToMany(Item::class)->using(ItemProject::class);
    }
}
