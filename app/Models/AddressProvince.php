<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class AddressProvince extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $fillable = ['name'];

    public function cities(): HasMany
    {
        return $this->hasMany(AddressCity::class, 'address_province_id');
    }
}
