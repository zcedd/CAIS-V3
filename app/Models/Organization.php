<?php

namespace App\Models;

use Spatie\Activitylog\LogOptions;
use Illuminate\Database\Eloquent\Model;
use Spatie\Activitylog\Traits\LogsActivity;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Organization extends Model
{
    use HasFactory;
    use SoftDeletes;
    use LogsActivity;

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
            ->setDescriptionForEvent(fn(string $eventName) => "This Organization model has been {$eventName}")
            ->dontSubmitEmptyLogs();
    }

    public function beneficiary()
    {
        return $this->belongsToMany(Beneficiary::class)->withTimestamps()->withSoftDeletes()->using(BeneficiaryOrganization::class);
    }

    public function president()
    {
        return $this->belongsTo(Beneficiary::class, 'beneficiary_id', 'id');
    }

    public function address()
    {
        return $this->belongsTo(AddrsBrgy::class, 'addrs_brgy_id', 'id');
    }

    public function assistance()
    {
        return $this->hasMany(Assistance::class);
    }

    public function beneficiaryPivot()
    {
        return $this->hasMany(BeneficiaryOrganization::class);
    }
}
