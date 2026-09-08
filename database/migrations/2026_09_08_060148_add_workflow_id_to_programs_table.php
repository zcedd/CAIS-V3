<?php

use App\Models\Department;
use App\Services\Workflow\EnsureDepartmentWorkflow;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
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

        $ensure = app(EnsureDepartmentWorkflow::class);

        Department::query()
            ->orderBy('id')
            ->each(static function (Department $department) use ($ensure): void {
                $ensure->defaultFor($department);
            });
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
};
