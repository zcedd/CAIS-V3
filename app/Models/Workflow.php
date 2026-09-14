<?php

namespace App\Models;

use App\Support\WorkflowTemplate;
use Database\Factories\WorkflowFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Workflow extends Model
{
    /** @use HasFactory<WorkflowFactory> */
    use HasFactory;

    use SoftDeletes;

    protected $fillable = [
        'department_id',
        'name',
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
            'is_default' => 'boolean',
            'template' => WorkflowTemplate::class,
        ];
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
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

    public function stepForStatus(int $requestStatusId): ?WorkflowStep
    {
        if ($this->relationLoaded('steps')) {
            return $this->steps->first(
                static fn (WorkflowStep $step): bool => (int) $step->request_status_id === $requestStatusId,
            );
        }

        return $this->steps()->where('request_status_id', $requestStatusId)->first();
    }
}
