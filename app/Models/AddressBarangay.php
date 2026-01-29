<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class AddressBarangay extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $fillable = ['address_city_id', 'name'];

    public function city()
    {
        return $this->belongsTo(AddressCity::class, 'address_city_id', 'id');
    }
}
