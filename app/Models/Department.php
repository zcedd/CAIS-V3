<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class Department extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $fillable = ['name', 'slug'];

    protected static function booted(): void
    {
        static::creating(function (Department $department): void {
            if (empty($department->slug)) {
                $department->slug = static::uniqueSlugFromName($department->name);
            }
        });

        static::updating(function (Department $department): void {
            if ($department->isDirty('name') && ! $department->isDirty('slug')) {
                $department->slug = static::uniqueSlugFromName($department->name, $department->id);
            }
        });
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    public static function uniqueSlugFromName(string $name, ?int $ignoreId = null): string
    {
        $base = Str::slug($name) !== '' ? Str::slug($name) : 'department';
        $slug = $base;
        $i = 2;
        while (static::query()
            ->where('slug', $slug)
            ->when($ignoreId !== null, fn ($query) => $query->where('id', '!=', $ignoreId))
            ->exists()) {
            $slug = $base.'-'.$i;
            $i++;
        }

        return $slug;
    }

    public function toSearchableArray()
    {
        $array = $this->toArray();

        return $array;
    }

    public function project()
    {
        return $this->hasMany(Project::class);
    }

    public function supervisor()
    {
        return $this->belongsToMany(User::class)->withTimestamps();
    }

    public function sector()
    {
        return $this->belongsToMany(Sector::class)->withTimestamps();
    }

    /**
     * Get all of the user for the Department
     */
    public function user(): HasMany
    {
        return $this->hasMany(User::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(Item::class);
    }
}
