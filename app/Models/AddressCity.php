<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class AddressCity extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = ['name', 'zipcode', 'excel_name', 'address_province_id'];

    public function province()
    {
        return $this->belongsTo(AddressProvince::class, 'address_province_id');
    }
}
