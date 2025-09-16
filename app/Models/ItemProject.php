<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Spatie\Activitylog\Traits\LogsActivity;
use Spatie\Activitylog\LogOptions;
use DDZobov\PivotSoftDeletes\Relations\Pivot;

class ItemProject extends Pivot
{
    use HasFactory;
    use LogsActivity;

    protected $fillable = ['project_id', 'item_id'];
    public $incrementing = true;

    public function project()
    {
        return $this->belongsTo(Project::class, 'project_id');
    }

    public function item()
    {
        return $this->belongsTo(Item::class, 'item_id');
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
        ->logFillable()
        ->useLogName('BeneficiaryOrganization')
        ->setDescriptionForEvent(fn(string $eventName) => "This BeneficiaryOrganization model has been {$eventName}");
    }
}
