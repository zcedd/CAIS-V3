<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class Beneficiary extends Model
{
    use HasFactory;
    use LogsActivity;
    use SoftDeletes;

    protected $fillable = [
        'cais_number',
        'beneficiable_type',
        'beneficiable_id',
    ];

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logFillable()
            ->useLogName('Beneficiary')
            ->setDescriptionForEvent(fn (string $eventName) => "This Beneficiary model has been {$eventName}")
            ->dontSubmitEmptyLogs();
    }

    public function beneficiable(): MorphTo
    {
        return $this->morphTo();
    }

    public function assistances(): HasMany
    {
        return $this->hasMany(Assistance::class);
    }
}
