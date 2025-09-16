<?php

namespace App\Models;

use Spatie\Searchable\Searchable;
use Spatie\Searchable\SearchResult;
use Illuminate\Database\Eloquent\Model;
use Laravel\Scout\Searchable as Search;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Department extends Model implements Searchable
{
    use HasFactory;
    use Search;
    use SoftDeletes;

    protected $fillable = ['name'];

    public function toSearchableArray()
    {
        $array = $this->toArray();
        return $array;
    }

    public function getSearchResult(): SearchResult
    {
        $url = route('department.project-list', $this->id);

        return new SearchResult(
            $this,
            $this->name,
            $url
        );
    }

    public function project()
    {
        return $this->hasMany(Project::class);
    }

    public function suprvisor()
    {
        return $this->belongsToMany(User::class)->withTimestamps();
    }

    public function sector()
    {
        return $this->belongsToMany(Sector::class)->withTimestamps();
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
