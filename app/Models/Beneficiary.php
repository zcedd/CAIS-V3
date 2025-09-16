<?php

namespace App\Models;

use Spatie\Searchable\Searchable;
// use Illuminate\Database\Eloquent\Model;
use Spatie\Activitylog\LogOptions;
use DDZobov\PivotSoftDeletes\Model;
use Spatie\Searchable\SearchResult;
use Laravel\Scout\Searchable as Search;
use Spatie\Activitylog\Traits\LogsActivity;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Beneficiary extends Model implements Searchable
{
    use HasFactory;
    use Search;
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

    public function toSearchableArray()
    {
        // $array = $this->toArray();
        $array['cais_number'] = $this->cais_number;
        $array['firstName'] = $this->firstName;
        $array['middleName'] = $this->middleName;
        $array['lastName'] = $this->lastName;
        $array['fullname'] = $this->firstName . ' ' . $this->middleName . ' ' . $this->lastName . ' ' . $this->suffix;
        $array['fullname3'] = $this->firstName . ' ' . $this->middleName . ' ' . $this->lastName;
        $array['fullname1'] = $this->firstName . ' ' . $this->lastName . ' ' . $this->suffix;
        $array['fullname2'] = $this->firstName . ' ' . $this->lastName;
        // return array('id' => $array['id'], 'firstName' => $array['firstName'], 'middleName' => $array['middleName'], 'lastName' => $array['lastName']);
        return $array;
    }

    public function getSearchResult(): SearchResult
    {
        $url = route('beneficiary.profile', $this->id);

        return new SearchResult(
            $this,
            $this->firstName . ' ' . $this->middleName . ' ' . $this->lastName . ' ' . $this->suffix,
            $url
        );
    }

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
        return $this->belongsTo(AddrsBrgy::class, 'brgy_id', 'id');
    }

    /**
     * Get the barangay that owns the Beneficiary
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function barangay(): BelongsTo
    {
        return $this->belongsTo(AddrsBrgy::class, 'brgy_id', 'id');
    }

    public function beneficiaryIdentification(): HasMany
    {
        return $this->hasMany(BeneficiaryIdentification::class);
    }

    public function identification(): BelongsToMany
    {
        return $this->belongsToMany(Identification::class)->withPivot('number')->withTimestamps()->withSoftDeletes()->using(BeneficiaryIdentification::class);
    }

    public function organization(): BelongsToMany
    {
        return $this->belongsToMany(Organization::class)->withTimestamps()->withSoftDeletes()->using(BeneficiaryOrganization::class);
    }

    public function civilStatus(): BelongsTo
    {
        return $this->belongsTo(CivilStatus::class);
    }

    public function assistance(): HasMany
    {
        return $this->hasMany(Assistance::class);
    }

    public function organizationPivot(): HasMany
    {
        return $this->hasMany(BeneficiaryOrganization::class);
    }
}
