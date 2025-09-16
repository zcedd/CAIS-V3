<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Spatie\Activitylog\Traits\LogsActivity;
use Spatie\Activitylog\LogOptions;
// use Illuminate\Database\Eloquent\Relations\Pivot;
use DDZobov\PivotSoftDeletes\Relations\Pivot;

class BeneficiaryIdentification extends Pivot
{
    use HasFactory;
    use LogsActivity;

    protected $fillable = ['beneficiary_id', 'identification_id', 'number'];
    public $incrementing = true;

    public function beneficiary(){
        return $this->belongsTo(Beneficiary::class);
    }

    public function identification(){
        return $this->belongsTo(Identification::class);
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
        ->logFillable()
        ->useLogName('Beneficiary Identification')
        ->setDescriptionForEvent(fn(string $eventName) => "This Beneficiary Identification model has been {$eventName}")
        ->dontSubmitEmptyLogs();
    }
}
