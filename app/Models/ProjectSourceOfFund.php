<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Pivot;

class ProjectSourceOfFund extends Pivot
{
    use HasFactory;

    public function project()
    {
        return $this->belongsToMany(Project::class)->withTimestamps()->withSoftDeletes()->using(BeneficiaryOrganization::class);
    }
}
