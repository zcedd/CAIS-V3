<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class ModeOfRequest extends Model
{
    use HasFactory;

    protected $fillable = ['name', 'created_at', 'updated_at'];

    public function assistance(): HasMany
    {
        return $this->hasMany(Assistance::class);
    }
}
