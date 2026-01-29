<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
// use Illuminate\Database\Eloquent\Relations\Pivot;
use Spatie\Activitylog\Traits\LogsActivity;
use Spatie\Activitylog\LogOptions;
use DDZobov\PivotSoftDeletes\Relations\Pivot;

class OrganizationMember extends Model
{
    use HasFactory;
    use LogsActivity;

    protected $fillable = ['individual_id', 'organization_id', 'role'];
    public $incrementing = true;

    public function organization()
    {
        return $this->belongsTo(Organization::class, 'organization_id');
    }

    public function individual()
    {
        return $this->belongsTo(Individual::class, 'individual_id');
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logFillable()
            ->useLogName('OrganizationMember')
            ->setDescriptionForEvent(fn(string $eventName) => "This OrganizationMember model has been {$eventName}");
    }
}
