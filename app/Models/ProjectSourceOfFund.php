<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\Pivot;

/**
 * @deprecated Use FundProgram instead.
 */
class ProjectSourceOfFund extends Pivot
{
    use HasFactory;

    protected $table = 'fund_program';

    public function project()
    {
        return $this->belongsToMany(Project::class)->withTimestamps()->withSoftDeletes()->using(BeneficiaryOrganization::class);
    }
}
