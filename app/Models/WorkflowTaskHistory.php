<?php

namespace App\Models;

use App\Enums\WorkflowTaskHistoryAction;
use App\Enums\WorkflowTaskStatus;
use Database\Factories\WorkflowTaskHistoryFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WorkflowTaskHistory extends Model
{
    /** @use HasFactory<WorkflowTaskHistoryFactory> */
    use HasFactory;

    public $timestamps = false;

    protected $fillable = [
        'workflow_task_id',
        'action',
        'from_status',
        'to_status',
        'performed_by',
        'remarks',
        'metadata',
        'created_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'action' => WorkflowTaskHistoryAction::class,
            'from_status' => WorkflowTaskStatus::class,
            'to_status' => WorkflowTaskStatus::class,
            'metadata' => 'array',
            'created_at' => 'datetime',
        ];
    }

    public function task(): BelongsTo
    {
        return $this->belongsTo(WorkflowTask::class, 'workflow_task_id');
    }

    public function performer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'performed_by');
    }
}
