<?php

namespace App\Models;

use App\Enums\DistributionApprovalMode;
use App\Enums\DistributionApprovalStrategy;
use Database\Factories\DistributionApprovalPolicyFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class DistributionApprovalPolicy extends Model
{
    /** @use HasFactory<DistributionApprovalPolicyFactory> */
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'name',
        'department_id',
        'program_id',
        'approval_mode',
        'strategy',
        'allow_partial_finalization',
        'allow_amount_modification',
        'allow_self_approval',
        'reschedule_requires_reapproval',
        'allow_resubmit_after_reject',
        'block_active_batch_reuse',
        'min_amount',
        'max_amount',
        'is_active',
        'created_by',
    ];

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'strategy' => 'sequential',
        'allow_partial_finalization' => false,
        'allow_amount_modification' => false,
        'allow_self_approval' => false,
        'reschedule_requires_reapproval' => true,
        'allow_resubmit_after_reject' => false,
        'block_active_batch_reuse' => true,
        'is_active' => true,
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'approval_mode' => DistributionApprovalMode::class,
            'strategy' => DistributionApprovalStrategy::class,
            'allow_partial_finalization' => 'boolean',
            'allow_amount_modification' => 'boolean',
            'allow_self_approval' => 'boolean',
            'reschedule_requires_reapproval' => 'boolean',
            'allow_resubmit_after_reject' => 'boolean',
            'block_active_batch_reuse' => 'boolean',
            'is_active' => 'boolean',
            'min_amount' => 'decimal:2',
            'max_amount' => 'decimal:2',
        ];
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    public function program(): BelongsTo
    {
        return $this->belongsTo(Program::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function stages(): HasMany
    {
        return $this->hasMany(DistributionApprovalStage::class)->orderBy('sequence');
    }
}
