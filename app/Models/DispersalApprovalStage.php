<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class DispersalApprovalStage extends Model
{
    protected $fillable = [
        'dispersal_approval_policy_id',
        'sequence',
        'name',
        'role_name',
        'user_id',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'sequence' => 'integer',
        ];
    }

    public function policy(): BelongsTo
    {
        return $this->belongsTo(DispersalApprovalPolicy::class, 'dispersal_approval_policy_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function tasks(): HasMany
    {
        return $this->hasMany(DispersalApprovalTask::class);
    }
}
