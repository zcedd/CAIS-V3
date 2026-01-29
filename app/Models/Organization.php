<?php

namespace App\Models;

use Spatie\Activitylog\LogOptions;
use Illuminate\Database\Eloquent\Model;
use Spatie\Activitylog\Traits\LogsActivity;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

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

    public function beneficiary(): BelongsToMany
    {
        return $this->belongsToMany(Individual::class)->withTimestamps()->withSoftDeletes()->using(OrganizationMember::class);
    }

    public function president(): BelongsTo
    {
        return $this->belongsTo(Individual::class, 'beneficiary_id', 'id');
    }

    public function address(): BelongsTo
    {
        return $this->belongsTo(AddressBarangay::class, 'address_barangay_id', 'id');
    }

    public function assistance(): HasMany
    {
        return $this->hasMany(AssistanceRequest::class);
    }

    public function memberPivot(): HasMany
    {
        return $this->hasMany(OrganizationMember::class);
    }
}
