<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Identification extends Model
{
    use HasFactory;

    public function individual()
    {
        return $this->belongsToMany(Individual::class)->withTimestamps()->withSoftDeletes()->using(IndividualIdentification::class);
    }
}
