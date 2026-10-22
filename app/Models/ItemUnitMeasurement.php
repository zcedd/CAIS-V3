<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ItemUnitMeasurement extends Model
{
    use HasFactory;

    public const CASH_NAME = 'php';

    protected $fillable = ['name', 'is_active'];

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'is_active' => true,
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    public function isProtected(): bool
    {
        return strcasecmp((string) $this->name, self::CASH_NAME) === 0;
    }

    public function items(): HasMany
    {
        return $this->hasMany(Item::class, 'item_unit_measurement_id');
    }
}
