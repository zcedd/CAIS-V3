<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class Organization extends Model
{
    use HasFactory;
    use LogsActivity;
    use SoftDeletes;

    protected $fillable = ['cais_number', 'name', 'beneficiary_id', 'addrs_brgy_id', 'mobile_number', 'total_member'];

    public function toSearchableArray()
    {
        $array = $this->toArray();

        return $array;
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logFillable()
            ->useLogName('Organization')
            ->setDescriptionForEvent(fn (string $eventName) => "This Organization model has been {$eventName}")
            ->dontSubmitEmptyLogs();
    }

    public function beneficiary()
    {
        return $this->belongsToMany(Individual::class, 'individual_organizations', 'organization_id', 'beneficiary_id')
            ->withTimestamps()
            ->using(BeneficiaryOrganization::class);
    }

    public function president()
    {
        return $this->belongsTo(Individual::class, 'beneficiary_id', 'id');
    }

    public function beneficiaryRecord(): MorphOne
    {
        return $this->morphOne(Beneficiary::class, 'beneficiable');
    }

    public function address()
    {
        return $this->belongsTo(AddrsBrgy::class, 'addrs_brgy_id', 'id');
    }

    public function assistance()
    {
        return $this->hasMany(Assistance::class, 'organization_id');
    }

    public function beneficiaryPivot()
    {
        return $this->hasMany(BeneficiaryOrganization::class);
    }
}
