<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\Pivot;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

/**
 * @deprecated This class is deprecated and should not be used in new code.
 * Use IndividualIdentification instead.
 */
class BeneficiaryIdentification extends Pivot
{
    use HasFactory;
    use LogsActivity;

    protected $table = 'individual_identification';

    protected $fillable = ['beneficiary_id', 'identification_id', 'number'];

    public $incrementing = true;

    public function beneficiary()
    {
        return $this->belongsTo(Individual::class, 'beneficiary_id');
    }

    public function identification()
    {
        return $this->belongsTo(Identification::class);
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logFillable()
            ->useLogName('Beneficiary Identification')
            ->setDescriptionForEvent(fn (string $eventName) => "This Beneficiary Identification model has been {$eventName}")
            ->dontSubmitEmptyLogs();
    }
}
