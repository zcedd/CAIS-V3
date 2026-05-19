<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Identification extends Model
{
    use HasFactory;

    public function beneficiary()
    {
        return $this->belongsToMany(Individual::class, 'individual_identification', 'identification_id', 'beneficiary_id')
            ->withPivot('number')
            ->withTimestamps()
            ->withSoftDeletes()
            ->using(BeneficiaryIdentification::class);
    }
}
