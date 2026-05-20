<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class Individual extends Model
{
    use HasFactory;
    use LogsActivity;
    use SoftDeletes;

    protected $fillable = [
        'cais_number',
        'first_name',
        'middle_name',
        'last_name',
        'suffix',
        'birthday',
        'sex',
        'other_address',
        'civil_status_id',
        'mobile_number',
        'indigenous',
        'ethnicity',
        'pwd',
        'is_4ps_beneficiary',
        'is_solo_parent',
        'spouse',
        'address_barangay_id',
        'created_at',
        'updated_at',
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
        return $this->belongsToMany(Organization::class, 'individual_organizations', 'beneficiary_id', 'organization_id')
            ->withTimestamps()
            ->withSoftDeletes()
            ->using(IndividualOrganization::class);
    }

    public function civilStatus(): BelongsTo
    {
        return $this->belongsTo(CivilStatus::class);
    }

    public function assistance(): HasMany
    {
        return $this->hasMany(Assistance::class, 'individual_id');
    }

    public function organizationPivot(): HasMany
    {
        return $this->hasMany(IndividualOrganization::class);
    }
}
