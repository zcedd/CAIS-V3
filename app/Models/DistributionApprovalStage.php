<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class DistributionApprovalStage extends Model
{
    protected $fillable = [
        'distribution_approval_policy_id',
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
        return $this->belongsTo(DistributionApprovalPolicy::class, 'distribution_approval_policy_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function tasks(): HasMany
    {
        return $this->hasMany(DistributionApprovalTask::class);
    }
}
