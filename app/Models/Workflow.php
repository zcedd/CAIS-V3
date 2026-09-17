<?php

namespace App\Models;

use App\Enums\WorkflowStatus;
use App\Enums\WorkflowTemplate;
use Database\Factories\WorkflowFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Database\Eloquent\SoftDeletes;

class Workflow extends Model
{
    /** @use HasFactory<WorkflowFactory> */
    use HasFactory;

    use SoftDeletes;

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'version' => 1,
        'status' => WorkflowStatus::Draft->value,
        'is_default' => false,
    ];

    protected $fillable = [
        'department_id',
        'name',
        'code',
        'description',
        'version',
        'status',
        'source_workflow_id',
        'template',
        'is_default',
        'staff_entry_request_status_id',
        'public_entry_request_status_id',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'version' => 'integer',
            'status' => WorkflowStatus::class,
            'is_default' => 'boolean',
            'template' => WorkflowTemplate::class,
        ];
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    public function sourceWorkflow(): BelongsTo
    {
        return $this->belongsTo(self::class, 'source_workflow_id');
    }

    public function versions(): HasMany
    {
        return $this->hasMany(self::class, 'source_workflow_id');
    }

    public function staffEntryStatus(): BelongsTo
    {
        return $this->belongsTo(RequestStatus::class, 'staff_entry_request_status_id');
    }

    public function publicEntryStatus(): BelongsTo
    {
        return $this->belongsTo(RequestStatus::class, 'public_entry_request_status_id');
    }

    public function steps(): HasMany
    {
        return $this->hasMany(WorkflowStep::class)->orderBy('sort_order')->orderBy('id');
    }

    public function programs(): HasMany
    {
        return $this->hasMany(Program::class);
    }

    public function instances(): HasMany
    {
        return $this->hasMany(AssistanceWorkflow::class);
    }

    public function assistances(): HasMany
    {
        return $this->hasMany(Assistance::class);
    }

    public function tasks(): HasManyThrough
    {
        return $this->hasManyThrough(WorkflowTask::class, AssistanceWorkflow::class);
    }

    public function isReferenced(): bool
    {
        if ($this->hasCountAttribute('programs_count')
            || $this->hasCountAttribute('instances_count')
            || $this->hasCountAttribute('assistances_count')) {
            return (int) ($this->programs_count ?? 0) > 0
                || (int) ($this->instances_count ?? 0) > 0
                || (int) ($this->assistances_count ?? 0) > 0;
        }

        return $this->programs()->exists()
            || $this->instances()->exists()
            || $this->assistances()->exists();
    }

    private function hasCountAttribute(string $attribute): bool
    {
        return array_key_exists($attribute, $this->attributes);
    }

    public function stepForStatus(int $requestStatusId): ?WorkflowStep
    {
        if ($this->relationLoaded('steps')) {
            return $this->steps->first(
                static fn (WorkflowStep $step): bool => (int) $step->request_status_id === $requestStatusId,
            );
        }

        return $this->steps()->where('request_status_id', $requestStatusId)->first();
    }

    public function startStep(): ?WorkflowStep
    {
        if ($this->relationLoaded('steps')) {
            return $this->steps->first(static fn (WorkflowStep $step): bool => $step->is_start)
                ?? $this->steps->first();
        }

        return $this->steps()->where('is_start', true)->first()
            ?? $this->steps()->orderBy('sort_order')->orderBy('id')->first();
    }

    public function isMutable(): bool
    {
        $status = $this->status instanceof WorkflowStatus
            ? $this->status
            : WorkflowStatus::tryFrom((string) $this->status) ?? WorkflowStatus::Active;

        return $status->isMutable();
    }
}
