<?php

namespace App\Models;

use Spatie\Searchable\Searchable;
use Spatie\Searchable\SearchResult;
use Illuminate\Database\Eloquent\Model;
use Laravel\Scout\Searchable as Search;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Sector extends Model
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
        $url = route('sector.department', $this->id);

        return new SearchResult(
            $this,
            $this->name,
            $url
        );
    }
    
    public function department()
    {
        return $this->belongsToMany(Department::class);
    }
}
