<?php

namespace App\Models;

use App\Enums\DistributionApprovalMode;
use App\Enums\DistributionBatchStatus;
use App\Enums\DistributionFinalizationScope;
use App\Enums\DistributionType;
use Database\Factories\DistributionBatchFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class DistributionBatch extends Model
{
    /** @use HasFactory<DistributionBatchFactory> */
    use HasFactory;

    use SoftDeletes;

    protected $fillable = [
        'batch_code',
        'program_id',
        'department_id',
        'distribution_approval_policy_id',
        'title',
        'description',
        'scheduled_at',
        'venue',
        'venue_details',
        'venue_latitude',
        'venue_longitude',
        'approval_mode',
        'distribution_type',
        'status',
        'revision',
        'lock_version',
        'finalization_scope',
        'created_by',
        'updated_by',
        'submitted_at',
        'approved_at',
        'opened_at',
        'locked_at',
        'completed_at',
        'closed_at',
        'cancelled_at',
        'cancellation_reason',
        'notes',
        'validation_summary',
        'beneficiary_count',
        'approved_beneficiary_count',
        'proposed_cash_total',
        'approved_cash_total',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'scheduled_at' => 'datetime',
            'submitted_at' => 'datetime',
            'approved_at' => 'datetime',
            'opened_at' => 'datetime',
            'locked_at' => 'datetime',
            'completed_at' => 'datetime',
            'closed_at' => 'datetime',
            'venue_latitude' => 'decimal:7',
            'venue_longitude' => 'decimal:7',
            'cancelled_at' => 'datetime',
            'approval_mode' => DistributionApprovalMode::class,
            'distribution_type' => DistributionType::class,
            'status' => DistributionBatchStatus::class,
            'finalization_scope' => DistributionFinalizationScope::class,
            'validation_summary' => 'array',
            'revision' => 'integer',
            'lock_version' => 'integer',
            'beneficiary_count' => 'integer',
            'approved_beneficiary_count' => 'integer',
            'proposed_cash_total' => 'decimal:2',
            'approved_cash_total' => 'decimal:2',
        ];
    }

    public function program(): BelongsTo
    {
        return $this->belongsTo(Program::class);
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    public function policy(): BelongsTo
    {
        return $this->belongsTo(DistributionApprovalPolicy::class, 'distribution_approval_policy_id')->withTrashed();
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function items(): HasMany
    {
        return $this->hasMany(DistributionBatchItem::class);
    }

    public function revisions(): HasMany
    {
        return $this->hasMany(DistributionBatchRevision::class);
    }

    public function tasks(): HasMany
    {
        return $this->hasMany(DistributionApprovalTask::class);
    }

    public function decisions(): HasMany
    {
        return $this->hasMany(DistributionApprovalDecision::class);
    }

    public function documents(): HasMany
    {
        return $this->hasMany(DistributionBatchDocument::class);
    }

    public function assignments(): HasMany
    {
        return $this->hasMany(DistributionBatchAssignment::class);
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(DistributionTransaction::class);
    }

    public function editor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function isEditable(): bool
    {
        return $this->status->isEditable() && $this->locked_at === null;
    }

    public function distributableItems(): HasMany
    {
        return $this->items()->where('approval_status', 'approved');
    }
}
