<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class AddressBarangay extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = ['name', 'address_city_id'];

    public function city()
    {
        return $this->belongsTo(AddressCity::class, 'address_city_id', 'id');
    }

    public function formattedLabel(): ?string
    {
        $this->loadMissing('city.province');

        $label = collect([
            $this->name,
            $this->city?->name,
            $this->city?->province?->name,
        ])
            ->filter(static fn(?string $part): bool => $part !== null && trim($part) !== '')
            ->implode(', ');

        return $label !== '' ? $label : null;
    }
}
