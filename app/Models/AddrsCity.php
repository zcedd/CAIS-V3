<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;


/**
 * @deprecated This class is deprecated. 
 * Please use the AddressCity model instead.
 */
#[\AllowDynamicProperties]
class AddrsCity extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $table = 'address_cities';

    protected $fillable = ['name', 'zipcode', 'excel_name'];
}
