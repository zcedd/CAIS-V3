<?php

namespace App\Models;

use Spatie\Searchable\SearchResult;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Department extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $fillable = ['name'];

    public function toSearchableArray()
    {
        $array = $this->toArray();
        return $array;
    }

    public function program(): HasMany
    {
        return $this->hasMany(Program::class);
    }

    public function supervisor(): BelongsToMany
    {
        return $this->belongsToMany(User::class)->withTimestamps();
    }

    public function sector(): BelongsToMany
    {
        return $this->belongsToMany(Sector::class);
    }

    /**
     * Get all of the user for the Department
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function user(): HasMany
    {
        return $this->hasMany(User::class);
    }
}
