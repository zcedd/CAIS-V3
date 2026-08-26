<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProgramEligibilityRule extends Model
{
    protected $fillable = [
        'program_id',
        'cooldown_days',
        'require_pwd',
        'require_4ps',
        'require_solo_parent',
        'require_indigenous',
    ];

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'require_pwd' => false,
        'require_4ps' => false,
        'require_solo_parent' => false,
        'require_indigenous' => false,
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'cooldown_days' => 'integer',
            'require_pwd' => 'boolean',
            'require_4ps' => 'boolean',
            'require_solo_parent' => 'boolean',
            'require_indigenous' => 'boolean',
        ];
    }

    public function program(): BelongsTo
    {
        return $this->belongsTo(Program::class);
    }

    public function hasDemographicRequirements(): bool
    {
        return $this->require_pwd
            || $this->require_4ps
            || $this->require_solo_parent
            || $this->require_indigenous;
    }
}
