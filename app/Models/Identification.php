<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
// use Illuminate\Database\Eloquent\Model;
use DDZobov\PivotSoftDeletes\Model;

class Identification extends Model
{
    use HasFactory;

    public function beneficiary(){
        return $this->belongsToMany(Beneficiary::class)->withTimestamps()->withSoftDeletes()->using(BeneficiaryIdentification::class);
    }
}
