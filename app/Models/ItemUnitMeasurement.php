<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ItemUnitMeasurement extends Model
{
    use HasFactory;

    protected $fillable = ['name'];

    public function items(): HasMany
    {
        return $this->hasMany(Item::class, 'item_unit_measurement_id');
    }
}
