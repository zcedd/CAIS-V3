<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
// use Illuminate\Database\Eloquent\Model;
// use Illuminate\Database\Eloquent\Relations\Pivot;
use Spatie\Activitylog\Traits\LogsActivity;
use Spatie\Activitylog\LogOptions;
use DDZobov\PivotSoftDeletes\Relations\Pivot;

class BeneficiaryOrganization extends Pivot
{
    use HasFactory;
    use LogsActivity;

    protected $fillable = ['beneficiary_id', 'organization_id'];
    public $incrementing = true;

    public function organization()
    {
        return $this->belongsTo(Organization::class, 'organization_id');
    }

    public function beneficiary()
    {
        return $this->belongsTo(Beneficiary::class, 'beneficiary_id');
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
        ->logFillable()
        ->useLogName('BeneficiaryOrganization')
        ->setDescriptionForEvent(fn(string $eventName) => "This BeneficiaryOrganization model has been {$eventName}");
    }
}
