<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use DDZobov\PivotSoftDeletes\Relations\Pivot;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class ProjectSourceOfFund extends Pivot
{
    use HasFactory;

    public function project(){
        return $this->belongsToMany(Project::class)->withTimestamps()->withSoftDeletes()->using(BeneficiaryOrganization::class);
    }
}
