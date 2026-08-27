<?php

namespace App\Models;

use App\Support\UnspscCodeLevel;
use Database\Factories\UnspscCodeFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class UnspscCode extends Model
{
    /** @use HasFactory<UnspscCodeFactory> */
    use HasFactory;

    protected $fillable = [
        'code',
        'title',
        'level',
        'parent_id',
        'segment_code',
        'family_code',
        'class_code',
        'is_curated',
        'version',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_curated' => 'boolean',
        ];
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(Item::class);
    }

    public function pathLabel(): string
    {
        $parts = [$this->title];
        $parent = $this->parent;

        while ($parent instanceof self) {
            array_unshift($parts, $parent->title);
            $parent = $parent->parent;
        }

        return implode(' > ', $parts);
    }

    public function isCommodity(): bool
    {
        return $this->level === UnspscCodeLevel::Commodity;
    }
}
