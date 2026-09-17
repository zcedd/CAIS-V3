<?php

use App\Enums\RequestStatusCode;
use App\Enums\WorkflowInstanceStatus;
use App\Enums\WorkflowStepType;
use App\Enums\WorkflowTaskStatus;
use App\Enums\WorkflowTransitionAction;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $this->dropUniqueIfExists('workflow_steps', 'workflow_steps_status_unique');

        if (! Schema::hasColumn('workflow_steps', 'name') || ! Schema::hasColumn('workflow_steps', 'step_type')) {
            Schema::table('workflow_steps', function (Blueprint $table) {
                if (! Schema::hasColumn('workflow_steps', 'name')) {
                    $table->string('name')->nullable()->after('code');
                }

                if (! Schema::hasColumn('workflow_steps', 'step_type')) {
                    $table->string('step_type')->default(WorkflowStepType::Custom->value)->after('name');
                }
            });
        }

        $this->dropTransitionStatusUnique();

        if (
            ! Schema::hasColumn('workflow_step_transitions', 'to_step_id')
            || ! Schema::hasColumn('workflow_step_transitions', 'action')
            || ! Schema::hasColumn('workflow_step_transitions', 'label')
            || ! Schema::hasColumn('workflow_step_transitions', 'requires_comment')
            || ! Schema::hasColumn('workflow_step_transitions', 'conditions')
        ) {
            Schema::table('workflow_step_transitions', function (Blueprint $table) {
                if (! Schema::hasColumn('workflow_step_transitions', 'to_step_id')) {
                    $table->foreignId('to_step_id')
                        ->nullable()
                        ->after('workflow_step_id')
                        ->constrained('workflow_steps')
                        ->restrictOnDelete();
                }

                if (! Schema::hasColumn('workflow_step_transitions', 'action')) {
                    $table->string('action')->default(WorkflowTransitionAction::Advance->value)->after('to_step_id');
                }

                if (! Schema::hasColumn('workflow_step_transitions', 'label')) {
                    $table->string('label')->nullable()->after('action');
                }

                if (! Schema::hasColumn('workflow_step_transitions', 'requires_comment')) {
                    $table->boolean('requires_comment')->default(false)->after('label');
                }

                if (! Schema::hasColumn('workflow_step_transitions', 'conditions')) {
                    $table->json('conditions')->nullable()->after('requires_comment');
                }
            });
        }

        if (! Schema::hasColumn('assistance_request_sub_status', 'recorded_by')) {
            Schema::table('assistance_request_sub_status', function (Blueprint $table) {
                $table->foreignId('recorded_by')
                    ->nullable()
                    ->after('remark')
                    ->constrained('users')
                    ->nullOnDelete();
            });
        }

        $this->backfillStepNamesAndTypes();
        $this->backfillTransitionSteps();

        $this->ensureUnique(
            'workflow_step_transitions',
            'workflow_step_to_step_action_unique',
            ['workflow_step_id', 'to_step_id', 'action'],
        );

        if (! Schema::hasTable('assistance_workflows')) {
            Schema::create('assistance_workflows', function (Blueprint $table) {
                $table->id();
                $table->foreignId('assistance_id')->unique()->constrained('assistances')->cascadeOnDelete();
                $table->foreignId('workflow_id')->constrained('workflows')->restrictOnDelete();
                $table->unsignedInteger('workflow_version')->default(1);
                $table->foreignId('current_step_id')->nullable()->constrained('workflow_steps')->nullOnDelete();
                $table->string('status')->default(WorkflowInstanceStatus::Active->value);
                $table->timestamp('started_at')->nullable();
                $table->timestamp('completed_at')->nullable();
                $table->timestamps();

                $table->index(['workflow_id', 'status']);
                $table->index(['current_step_id', 'status']);
            });
        }

        if (! Schema::hasTable('workflow_tasks')) {
            Schema::create('workflow_tasks', function (Blueprint $table) {
                $table->id();
                $table->foreignId('assistance_workflow_id')->constrained('assistance_workflows')->cascadeOnDelete();
                $table->foreignId('workflow_step_id')->constrained('workflow_steps')->restrictOnDelete();
                $table->foreignId('assigned_to_id')->nullable()->constrained('users')->nullOnDelete();
                $table->foreignId('assigned_by_id')->nullable()->constrained('users')->nullOnDelete();
                $table->string('status')->default(WorkflowTaskStatus::Pending->value);
                $table->string('priority')->default('normal');
                $table->timestamp('assigned_at')->nullable();
                $table->timestamp('started_at')->nullable();
                $table->timestamp('due_at')->nullable();
                $table->timestamp('completed_at')->nullable();
                $table->text('remarks')->nullable();
                $table->timestamps();

                $table->index(['assigned_to_id', 'status']);
                $table->index(['workflow_step_id', 'status']);
                $table->index(['status', 'due_at']);
            });
        }

        if (! Schema::hasTable('workflow_task_histories')) {
            Schema::create('workflow_task_histories', function (Blueprint $table) {
                $table->id();
                $table->foreignId('workflow_task_id')->constrained('workflow_tasks')->cascadeOnDelete();
                $table->string('action');
                $table->string('from_status')->nullable();
                $table->string('to_status')->nullable();
                $table->foreignId('performed_by')->nullable()->constrained('users')->nullOnDelete();
                $table->text('remarks')->nullable();
                $table->json('metadata')->nullable();
                $table->timestamp('created_at')->useCurrent();
            });
        }

        $this->backfillInstancesAndTasks();
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('workflow_task_histories');
        Schema::dropIfExists('workflow_tasks');
        Schema::dropIfExists('assistance_workflows');

        Schema::table('assistance_request_sub_status', function (Blueprint $table) {
            $table->dropConstrainedForeignId('recorded_by');
        });

        Schema::table('workflow_step_transitions', function (Blueprint $table) {
            $table->dropUnique('workflow_step_to_step_action_unique');
            $table->dropConstrainedForeignId('to_step_id');
            $table->dropColumn(['action', 'label', 'requires_comment', 'conditions']);
            $table->unique(['workflow_step_id', 'to_request_status_id'], 'workflow_step_to_status_unique');
        });

        Schema::table('workflow_steps', function (Blueprint $table) {
            $table->dropColumn(['name', 'step_type']);
            $table->unique(['workflow_id', 'request_status_id'], 'workflow_steps_status_unique');
        });
    }

    private function backfillStepNamesAndTypes(): void
    {
        $steps = DB::table('workflow_steps')
            ->join('request_statuses', 'request_statuses.id', '=', 'workflow_steps.request_status_id')
            ->get([
                'workflow_steps.id',
                'workflow_steps.code',
                'workflow_steps.is_start',
                'workflow_steps.is_end',
                'request_statuses.name as status_name',
                'request_statuses.code as status_code',
                'request_statuses.is_hold',
                'request_statuses.is_terminal',
            ]);

        foreach ($steps as $step) {
            $statusCode = (string) ($step->status_code ?: '');
            $type = match (true) {
                (bool) $step->is_start => WorkflowStepType::Start->value,
                (bool) $step->is_end || $statusCode === RequestStatusCode::Closed->value => WorkflowStepType::Completion->value,
                $statusCode === RequestStatusCode::Review->value => WorkflowStepType::Verification->value,
                $statusCode === RequestStatusCode::Approved->value => WorkflowStepType::Approval->value,
                $statusCode === RequestStatusCode::Delivered->value => WorkflowStepType::Release->value,
                $statusCode === RequestStatusCode::Denied->value => WorkflowStepType::Rejection->value,
                (bool) $step->is_hold || $statusCode === RequestStatusCode::OnHold->value => WorkflowStepType::Hold->value,
                default => WorkflowStepType::Custom->value,
            };

            $name = trim((string) $step->status_name);
            if ($name === '') {
                $name = str_replace('_', ' ', (string) $step->code);
            }

            DB::table('workflow_steps')->where('id', $step->id)->update([
                'name' => $name !== '' ? $name : 'Step',
                'step_type' => $type,
            ]);
        }
    }

    private function backfillTransitionSteps(): void
    {
        $steps = DB::table('workflow_steps')
            ->orderBy('workflow_id')
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get(['id', 'workflow_id', 'request_status_id']);

        $stepByWorkflowStatus = [];

        foreach ($steps as $step) {
            $key = $step->workflow_id.'|'.$step->request_status_id;
            if (! isset($stepByWorkflowStatus[$key])) {
                $stepByWorkflowStatus[$key] = (int) $step->id;
            }
        }

        $transitions = DB::table('workflow_step_transitions')
            ->join('workflow_steps', 'workflow_steps.id', '=', 'workflow_step_transitions.workflow_step_id')
            ->leftJoin('request_statuses', 'request_statuses.id', '=', 'workflow_step_transitions.to_request_status_id')
            ->get([
                'workflow_step_transitions.id',
                'workflow_steps.workflow_id',
                'workflow_step_transitions.to_request_status_id',
                'request_statuses.code as status_code',
            ]);

        foreach ($transitions as $transition) {
            $key = $transition->workflow_id.'|'.$transition->to_request_status_id;
            $toStepId = $stepByWorkflowStatus[$key] ?? null;
            $statusCode = (string) ($transition->status_code ?: '');
            $action = match ($statusCode) {
                RequestStatusCode::Denied->value => WorkflowTransitionAction::Reject->value,
                RequestStatusCode::OnHold->value => WorkflowTransitionAction::Hold->value,
                RequestStatusCode::Closed->value => WorkflowTransitionAction::Close->value,
                default => WorkflowTransitionAction::Advance->value,
            };

            DB::table('workflow_step_transitions')->where('id', $transition->id)->update([
                'to_step_id' => $toStepId,
                'action' => $action,
                'label' => null,
                'requires_comment' => in_array($action, [
                    WorkflowTransitionAction::Reject->value,
                    WorkflowTransitionAction::Return->value,
                ], true),
            ]);
        }
    }

    private function backfillInstancesAndTasks(): void
    {
        $assistances = DB::table('assistances')
            ->leftJoin('request_sub_statuses', 'request_sub_statuses.id', '=', 'assistances.current_request_sub_status_id')
            ->leftJoin('request_statuses', 'request_statuses.id', '=', 'request_sub_statuses.request_status_id')
            ->whereNull('assistances.deleted_at')
            ->whereNotNull('assistances.workflow_id')
            ->orderBy('assistances.id')
            ->get([
                'assistances.id',
                'assistances.workflow_id',
                'assistances.assigned_to_id',
                'assistances.assigned_at',
                'assistances.sla_due_at',
                'assistances.created_at',
                'request_statuses.id as status_id',
                'request_statuses.code as status_code',
                'request_statuses.is_terminal',
            ])
            ->unique('id')
            ->values();

        $workflows = DB::table('workflows')->get(['id', 'version'])->keyBy('id');
        $steps = DB::table('workflow_steps')
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get(['id', 'workflow_id', 'request_status_id', 'is_start', 'is_end']);

        $stepsByWorkflow = $steps->groupBy('workflow_id');
        $existingAssistanceIds = DB::table('assistance_workflows')->pluck('assistance_id')->flip();
        $now = now();

        foreach ($assistances as $assistance) {
            if (isset($existingAssistanceIds[$assistance->id])) {
                continue;
            }

            $workflow = $workflows->get($assistance->workflow_id);

            if ($workflow === null) {
                continue;
            }

            $workflowSteps = $stepsByWorkflow->get($assistance->workflow_id, collect());
            $currentStep = $workflowSteps->first(
                static fn ($step): bool => (int) $step->request_status_id === (int) $assistance->status_id,
            ) ?? $workflowSteps->first(static fn ($step): bool => (bool) $step->is_start);

            $isTerminal = (bool) $assistance->is_terminal
                || in_array((string) $assistance->status_code, RequestStatusCode::terminalValues(), true);
            $status = $isTerminal
                ? WorkflowInstanceStatus::Completed->value
                : WorkflowInstanceStatus::Active->value;

            $instanceId = DB::table('assistance_workflows')->insertGetId([
                'assistance_id' => $assistance->id,
                'workflow_id' => $assistance->workflow_id,
                'workflow_version' => (int) $workflow->version,
                'current_step_id' => $currentStep?->id,
                'status' => $status,
                'started_at' => $assistance->created_at,
                'completed_at' => $isTerminal ? $now : null,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
            $existingAssistanceIds[$assistance->id] = true;

            if ($isTerminal || $currentStep === null || (bool) $currentStep->is_end) {
                continue;
            }

            $taskStatus = $assistance->assigned_to_id !== null
                ? WorkflowTaskStatus::InProgress->value
                : WorkflowTaskStatus::Pending->value;

            $taskId = DB::table('workflow_tasks')->insertGetId([
                'assistance_workflow_id' => $instanceId,
                'workflow_step_id' => $currentStep->id,
                'assigned_to_id' => $assistance->assigned_to_id,
                'assigned_by_id' => null,
                'status' => $taskStatus,
                'priority' => 'normal',
                'assigned_at' => $assistance->assigned_at,
                'started_at' => $assistance->assigned_at,
                'due_at' => $assistance->sla_due_at,
                'completed_at' => null,
                'remarks' => null,
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            DB::table('workflow_task_histories')->insert([
                'workflow_task_id' => $taskId,
                'action' => 'created',
                'from_status' => null,
                'to_status' => $taskStatus,
                'performed_by' => null,
                'remarks' => 'Backfilled from existing assistance',
                'metadata' => json_encode(['source' => 'migration']),
                'created_at' => $now,
            ]);
        }
    }

    private function dropUniqueIfExists(string $table, string $index): void
    {
        if (! Schema::hasIndex($table, $index)) {
            return;
        }

        Schema::table($table, function (Blueprint $blueprint) use ($index): void {
            $blueprint->dropUnique($index);
        });
    }

    private function dropTransitionStatusUnique(): void
    {
        $table = 'workflow_step_transitions';

        if (! Schema::hasIndex($table, 'workflow_step_to_status_unique')) {
            return;
        }

        Schema::table($table, function (Blueprint $blueprint) use ($table): void {
            if (Schema::hasForeignKey($table, ['workflow_step_id'])) {
                $blueprint->dropForeign(['workflow_step_id']);
            }

            if (Schema::hasForeignKey($table, ['to_request_status_id'])) {
                $blueprint->dropForeign(['to_request_status_id']);
            }

            $blueprint->dropUnique('workflow_step_to_status_unique');
        });

        Schema::table($table, function (Blueprint $blueprint) use ($table): void {
            if (! Schema::hasForeignKey($table, ['workflow_step_id'])) {
                $blueprint->foreign('workflow_step_id')
                    ->references('id')
                    ->on('workflow_steps')
                    ->cascadeOnDelete();
            }

            if (! Schema::hasForeignKey($table, ['to_request_status_id'])) {
                $blueprint->foreign('to_request_status_id')
                    ->references('id')
                    ->on('request_statuses')
                    ->restrictOnDelete();
            }
        });
    }

    /**
     * @param  list<string>  $columns
     */
    private function ensureUnique(string $table, string $index, array $columns): void
    {
        if (Schema::hasIndex($table, $index)) {
            return;
        }

        Schema::table($table, function (Blueprint $blueprint) use ($index, $columns): void {
            $blueprint->unique($columns, $index);
        });
    }
};
