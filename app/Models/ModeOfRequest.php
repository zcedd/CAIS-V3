<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ModeOfRequest extends Model
{
    use HasFactory;

    public const PUBLIC_INTAKE_NAME = 'Online';

    protected $fillable = ['name', 'is_active', 'created_at', 'updated_at'];

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
        return strcasecmp((string) $this->name, self::PUBLIC_INTAKE_NAME) === 0;
    }

    public function assistance(): HasMany
    {
        return $this->hasMany(Assistance::class);
    }
}
