<?php

namespace App\Models;

use App\Enums\DisbursementApprovalMode;
use App\Enums\DisbursementBatchStatus;
use App\Enums\DisbursementFinalizationScope;
use Database\Factories\DisbursementBatchFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class DisbursementBatch extends Model
{
    /** @use HasFactory<DisbursementBatchFactory> */
    use HasFactory;

    use SoftDeletes;

    protected $fillable = [
        'batch_code',
        'program_id',
        'department_id',
        'disbursement_approval_policy_id',
        'title',
        'description',
        'scheduled_at',
        'venue',
        'venue_details',
        'venue_latitude',
        'venue_longitude',
        'approval_mode',
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
            'approval_mode' => DisbursementApprovalMode::class,
            'status' => DisbursementBatchStatus::class,
            'finalization_scope' => DisbursementFinalizationScope::class,
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
        return $this->belongsTo(DisbursementApprovalPolicy::class, 'disbursement_approval_policy_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function items(): HasMany
    {
        return $this->hasMany(DisbursementBatchItem::class);
    }

    public function revisions(): HasMany
    {
        return $this->hasMany(DisbursementBatchRevision::class);
    }

    public function tasks(): HasMany
    {
        return $this->hasMany(DisbursementApprovalTask::class);
    }

    public function decisions(): HasMany
    {
        return $this->hasMany(DisbursementApprovalDecision::class);
    }

    public function documents(): HasMany
    {
        return $this->hasMany(DisbursementBatchDocument::class);
    }

    public function assignments(): HasMany
    {
        return $this->hasMany(DisbursementBatchAssignment::class);
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(DisbursementTransaction::class);
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
