<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Spatie\Activitylog\Traits\LogsActivity;
use Spatie\Activitylog\LogOptions;

class IndividualIdentification extends Model
{
    use HasFactory;
    use LogsActivity;

    protected $fillable = ['individual_id', 'identification_id', 'number'];
    public $incrementing = true;

    public function individual()
    {
        return $this->belongsTo(Individual::class);
    }

    public function identification()
    {
        return $this->belongsTo(Identification::class);
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logFillable()
            ->useLogName('Individual Identification')
            ->setDescriptionForEvent(fn(string $eventName) => "This Individual Identification model has been {$eventName}")
            ->dontSubmitEmptyLogs();
    }
}
