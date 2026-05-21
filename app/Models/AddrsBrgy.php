<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @deprecated This class is deprecated. 
 * Please use the AddressBarangay model instead.
 */
#[\AllowDynamicProperties]
class AddrsBrgy extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $table = 'address_barangays';

    protected $fillable = ['address_city_id', 'name'];

    public function city()
    {
        return $this->belongsTo(AddressCity::class, 'address_city_id', 'id');
    }
}
