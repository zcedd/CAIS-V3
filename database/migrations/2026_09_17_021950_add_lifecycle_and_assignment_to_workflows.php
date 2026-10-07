<?php

use App\Enums\WorkflowAssignmentType;
use App\Enums\WorkflowStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('workflows', function (Blueprint $table) {
            if (! Schema::hasColumn('workflows', 'code')) {
                $table->string('code', 64)->nullable()->after('name');
            }

            if (! Schema::hasColumn('workflows', 'description')) {
                $table->text('description')->nullable()->after('code');
            }

            if (! Schema::hasColumn('workflows', 'version')) {
                $table->unsignedInteger('version')->default(1)->after('description');
            }

            if (! Schema::hasColumn('workflows', 'status')) {
                $table->string('status')->default(WorkflowStatus::Active->value)->after('version');
            }

            if (! Schema::hasColumn('workflows', 'source_workflow_id')) {
                $table->foreignId('source_workflow_id')
                    ->nullable()
                    ->after('status')
                    ->constrained('workflows')
                    ->nullOnDelete();
            }
        });

        Schema::table('workflow_steps', function (Blueprint $table) {
            if (! Schema::hasColumn('workflow_steps', 'code')) {
                $table->string('code', 64)->nullable()->after('workflow_id');
            }

            if (! Schema::hasColumn('workflow_steps', 'is_start')) {
                $table->boolean('is_start')->default(false)->after('sort_order');
            }

            if (! Schema::hasColumn('workflow_steps', 'is_end')) {
                $table->boolean('is_end')->default(false)->after('is_start');
            }

            if (! Schema::hasColumn('workflow_steps', 'assignment_type')) {
                $table->string('assignment_type')->default(WorkflowAssignmentType::None->value)->after('requires_assignee');
            }

            if (! Schema::hasColumn('workflow_steps', 'assigned_role')) {
                $table->string('assigned_role')->nullable()->after('assignment_type');
            }

            if (! Schema::hasColumn('workflow_steps', 'assigned_department_id')) {
                $table->foreignId('assigned_department_id')
                    ->nullable()
                    ->after('assigned_role')
                    ->constrained('departments')
                    ->nullOnDelete();
            }

            if (! Schema::hasColumn('workflow_steps', 'automatic_assignment')) {
                $table->boolean('automatic_assignment')->default(false)->after('assigned_department_id');
            }
        });

        Schema::table('assistances', function (Blueprint $table) {
            if (! Schema::hasColumn('assistances', 'workflow_id')) {
                $table->foreignId('workflow_id')
                    ->nullable()
                    ->after('program_id')
                    ->constrained('workflows')
                    ->nullOnDelete();
            }
        });

        $this->backfillWorkflowLifecycle();
        $this->backfillStepLifecycle();
        $this->backfillAssistanceWorkflowSnapshots();

        Schema::table('workflows', function (Blueprint $table) {
            $table->unique(['department_id', 'code', 'version'], 'workflows_department_code_version_unique');
        });

        Schema::table('workflow_steps', function (Blueprint $table) {
            $table->unique(['workflow_id', 'code'], 'workflow_steps_workflow_code_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('workflow_steps', function (Blueprint $table) {
            $table->dropUnique('workflow_steps_workflow_code_unique');
        });

        Schema::table('workflows', function (Blueprint $table) {
            $table->dropUnique('workflows_department_code_version_unique');
        });

        Schema::table('assistances', function (Blueprint $table) {
            if (Schema::hasColumn('assistances', 'workflow_id')) {
                $table->dropConstrainedForeignId('workflow_id');
            }
        });

        Schema::table('workflow_steps', function (Blueprint $table) {
            if (Schema::hasColumn('workflow_steps', 'assigned_department_id')) {
                $table->dropConstrainedForeignId('assigned_department_id');
            }

            $table->dropColumn([
                'code',
                'is_start',
                'is_end',
                'assignment_type',
                'assigned_role',
                'automatic_assignment',
            ]);
        });

        Schema::table('workflows', function (Blueprint $table) {
            if (Schema::hasColumn('workflows', 'source_workflow_id')) {
                $table->dropConstrainedForeignId('source_workflow_id');
            }

            $table->dropColumn(['code', 'description', 'version', 'status']);
        });
    }

    private function backfillWorkflowLifecycle(): void
    {
        $used = [];

        foreach (DB::table('workflows')->orderBy('id')->get() as $workflow) {
            $base = Str::upper(Str::slug((string) $workflow->name, '_'));
            $base = $base !== '' ? $base : 'WORKFLOW';
            $code = $base;
            $suffix = 2;
            $key = $workflow->department_id.'|'.$code.'|1';

            while (isset($used[$key])) {
                $code = $base.'_'.$suffix;
                $suffix++;
                $key = $workflow->department_id.'|'.$code.'|1';
            }

            $used[$key] = true;

            DB::table('workflows')->where('id', $workflow->id)->update([
                'code' => $code,
                'version' => 1,
                'status' => WorkflowStatus::Active->value,
            ]);
        }
    }

    private function backfillStepLifecycle(): void
    {
        $steps = DB::table('workflow_steps')
            ->orderBy('workflow_id')
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();

        $usedCodes = [];

        foreach ($steps as $index => $step) {
            $status = DB::table('request_statuses')->where('id', $step->request_status_id)->first();
            $base = Str::upper(Str::slug((string) ($status->code ?? $status->name ?? 'STEP'), '_'));
            $base = $base !== '' ? $base : 'STEP';
            $code = $base;
            $suffix = 2;
            $key = $step->workflow_id.'|'.$code;

            while (isset($usedCodes[$key])) {
                $code = $base.'_'.$suffix;
                $suffix++;
                $key = $step->workflow_id.'|'.$code;
            }

            $usedCodes[$key] = true;

            $siblings = $steps->where('workflow_id', $step->workflow_id)->values();
            $isFirst = (int) $siblings->first()?->id === (int) $step->id;
            $isLastHappy = $status !== null && in_array((string) $status->code, ['closed'], true);

            DB::table('workflow_steps')->where('id', $step->id)->update([
                'code' => $code,
                'is_start' => $isFirst,
                'is_end' => $isLastHappy,
                'assignment_type' => $step->assigned_to_id !== null
                    ? WorkflowAssignmentType::User->value
                    : WorkflowAssignmentType::None->value,
                'automatic_assignment' => $step->assigned_to_id !== null,
            ]);
        }
    }

    private function backfillAssistanceWorkflowSnapshots(): void
    {
        $programWorkflows = DB::table('programs')->pluck('workflow_id', 'id');
        $departmentDefaults = DB::table('workflows')
            ->where('is_default', true)
            ->pluck('id', 'department_id');

        DB::table('assistances')
            ->whereNull('deleted_at')
            ->whereNull('workflow_id')
            ->orderBy('id')
            ->chunkById(200, function ($assistances) use ($programWorkflows, $departmentDefaults): void {
                foreach ($assistances as $assistance) {
                    $program = DB::table('programs')->where('id', $assistance->program_id)->first();
                    $workflowId = $programWorkflows[$assistance->program_id] ?? null;

                    if ($workflowId === null && $program?->parent_id !== null) {
                        $workflowId = $programWorkflows[$program->parent_id] ?? null;
                    }

                    if ($workflowId === null && $program !== null) {
                        $workflowId = $departmentDefaults[$program->department_id] ?? null;
                    }

                    if ($workflowId === null) {
                        continue;
                    }

                    DB::table('assistances')->where('id', $assistance->id)->update([
                        'workflow_id' => $workflowId,
                    ]);
                }
            });
    }
};
