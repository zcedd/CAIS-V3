<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Point programs at the active version of the workflow they were assigned.
     * Retired versions stay on assistance requests that already started.
     */
    public function up(): void
    {
        $activeWorkflows = DB::table('workflows')
            ->where('status', 'active')
            ->whereNull('deleted_at')
            ->orderByDesc('version')
            ->get(['id', 'department_id', 'code', 'is_default']);

        $seen = [];

        foreach ($activeWorkflows as $workflow) {
            $key = $workflow->department_id.'|'.$workflow->code;

            if (isset($seen[$key])) {
                continue;
            }

            $seen[$key] = true;

            $previousIds = DB::table('workflows')
                ->where('department_id', $workflow->department_id)
                ->where('code', $workflow->code)
                ->where('id', '!=', $workflow->id)
                ->whereNull('deleted_at')
                ->pluck('id');

            if ($previousIds->isEmpty()) {
                continue;
            }

            DB::table('programs')
                ->whereIn('workflow_id', $previousIds)
                ->update([
                    'workflow_id' => $workflow->id,
                    'updated_at' => now(),
                ]);

            $previousIsDefault = DB::table('workflows')
                ->whereIn('id', $previousIds)
                ->where('is_default', true)
                ->exists();

            if (! $previousIsDefault) {
                continue;
            }

            DB::table('workflows')
                ->whereIn('id', $previousIds)
                ->update([
                    'is_default' => false,
                    'updated_at' => now(),
                ]);

            if (! $workflow->is_default) {
                DB::table('workflows')
                    ->where('id', $workflow->id)
                    ->update([
                        'is_default' => true,
                        'updated_at' => now(),
                    ]);
            }
        }
    }

    /**
     * Retired workflow assignments are not restored.
     */
    public function down(): void
    {
        //
    }
};
