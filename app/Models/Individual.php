<?php

namespace App\Models;

use Spatie\Activitylog\LogOptions;
use Illuminate\Database\Eloquent\Model;
use Spatie\Activitylog\Traits\LogsActivity;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Individual extends Model
{
    use HasFactory;
    use SoftDeletes;
    use LogsActivity;

    protected $fillable = [
        'cais_number',
        'firstName',
        'middleName',
        'lastName',
        'suffix',
        'birthday',
        'sex',
        'other_address',
        'civil_status_id',
        'mobileNumber',
        'indigenous',
        'ethnicity',
        'pwd',
        'is_4ps_beneficiary',
        'is_solo_parent',
        'spouse',
        'brgy_id',
        'created_at',
        'updated_at'
    ];

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logFillable()
            ->useLogName('Beneficiary')
            ->setDescriptionForEvent(fn(string $eventName) => "This Beneficiary model has been {$eventName}")
            ->dontSubmitEmptyLogs();
    }

    public function address(): BelongsTo
    {
        return $this->belongsTo(AddressBarangay::class, 'address_barangay_id', 'id');
    }

    /**
     * Get the barangay that owns the Beneficiary
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function barangay(): BelongsTo
    {
        return $this->belongsTo(AddressBarangay::class, 'address_barangay_id', 'id');
    }

    public function beneficiaryIdentification(): HasMany
    {
        return $this->hasMany(IndividualIdentification::class);
    }

    public function identification(): BelongsToMany
    {
        return $this->belongsToMany(Identification::class)->withPivot('number')->withTimestamps()->withSoftDeletes()->using(IndividualIdentification::class);
    }

    public function organization(): BelongsToMany
    {
        return $this->belongsToMany(Organization::class)->withTimestamps()->withSoftDeletes()->using(OrganizationMember::class);
    }

    public function civilStatus(): BelongsTo
    {
        return $this->belongsTo(CivilStatus::class);
    }

    public function assistance(): HasMany
    {
        return $this->hasMany(AssistanceRequest::class);
    }

    public function organizationPivot(): HasMany
    {
        return $this->hasMany(OrganizationMember::class);
    }
}
