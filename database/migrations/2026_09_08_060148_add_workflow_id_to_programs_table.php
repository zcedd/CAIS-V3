<?php

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
        if (! Schema::hasColumn('programs', 'workflow_id')) {
            Schema::table('programs', function (Blueprint $table) {
                $table->foreignId('workflow_id')->nullable()->after('department_id')->constrained('workflows')->nullOnDelete();
            });
        }

        $this->seedDepartmentDefaults();
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('programs', function (Blueprint $table) {
            $table->dropConstrainedForeignId('workflow_id');
        });
    }

    /**
     * Insert a default workflow using only the columns that exist before the lifecycle migration.
     */
    private function seedDepartmentDefaults(): void
    {
        $now = now();
        $statuses = DB::table('request_statuses')->pluck('id', 'code');
        $reasons = DB::table('request_sub_statuses')->pluck('id', 'code');
        $submittedId = $statuses['submitted'] ?? null;

        if ($submittedId === null) {
            return;
        }

        $stages = [
            ['code' => 'submitted', 'reason' => 'awaiting_review', 'sort' => 10, 'sla' => 48],
            ['code' => 'review', 'reason' => 'under_review', 'sort' => 20, 'sla' => 72],
            ['code' => 'approved', 'reason' => 'approved', 'sort' => 30, 'sla' => 48],
            ['code' => 'delivered', 'reason' => 'delivered', 'sort' => 40, 'sla' => null],
            ['code' => 'closed', 'reason' => 'closed', 'sort' => 50, 'sla' => null],
            ['code' => 'on_hold', 'reason' => 'awaiting_information', 'sort' => 60, 'sla' => null],
            ['code' => 'denied', 'reason' => 'denied', 'sort' => 70, 'sla' => null],
        ];

        $transitions = [
            'submitted' => ['review', 'on_hold', 'denied', 'closed'],
            'review' => ['approved', 'on_hold', 'denied', 'closed'],
            'approved' => ['delivered', 'on_hold', 'denied', 'closed'],
            'delivered' => ['closed'],
            'on_hold' => ['submitted', 'denied', 'closed'],
            'denied' => ['closed'],
        ];

        foreach (DB::table('departments')->orderBy('id')->pluck('id') as $departmentId) {
            $alreadySeeded = DB::table('workflows')
                ->where('department_id', $departmentId)
                ->where('is_default', true)
                ->whereNull('deleted_at')
                ->exists();

            if ($alreadySeeded) {
                continue;
            }

            $workflowId = DB::table('workflows')->insertGetId([
                'department_id' => $departmentId,
                'name' => 'Standard Assistance Workflow',
                'template' => 'standard',
                'is_default' => true,
                'staff_entry_request_status_id' => $submittedId,
                'public_entry_request_status_id' => $submittedId,
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            $stepIds = [];

            foreach ($stages as $stage) {
                $statusId = $statuses[$stage['code']] ?? null;

                if ($statusId === null) {
                    continue;
                }

                $stepIds[$stage['code']] = DB::table('workflow_steps')->insertGetId([
                    'workflow_id' => $workflowId,
                    'request_status_id' => $statusId,
                    'sort_order' => $stage['sort'],
                    'default_request_sub_status_id' => $reasons[$stage['reason']] ?? null,
                    'sla_hours' => $stage['sla'],
                    'requires_assignee' => false,
                    'permission' => null,
                    'allows_skip_to_deliver' => false,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }

            foreach ($transitions as $from => $targets) {
                $fromStepId = $stepIds[$from] ?? null;

                if ($fromStepId === null) {
                    continue;
                }

                foreach ($targets as $target) {
                    $statusId = $statuses[$target] ?? null;

                    if ($statusId === null) {
                        continue;
                    }

                    DB::table('workflow_step_transitions')->insert([
                        'workflow_step_id' => $fromStepId,
                        'to_request_status_id' => $statusId,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ]);
                }
            }
        }
    }
};
