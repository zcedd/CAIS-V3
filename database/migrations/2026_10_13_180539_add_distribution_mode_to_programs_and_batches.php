<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('programs', function (Blueprint $table): void {
            $table->string('distribution_mode', 32)->default('batch')->after('public_intake');
        });

        Schema::table('distribution_batches', function (Blueprint $table): void {
            $table->string('distribution_type', 32)->default('batch')->after('approval_mode');
            $table->index(['department_id', 'distribution_type'], 'distribution_batches_department_type_index');
        });

        DB::table('distribution_batches')
            ->where('approval_mode', 'beneficiary')
            ->where('beneficiary_count', 1)
            ->whereIn('status', ['in_progress', 'completed'])
            ->whereNotExists(function ($query): void {
                $query->select(DB::raw(1))
                    ->from('distribution_approval_tasks')
                    ->whereColumn(
                        'distribution_approval_tasks.distribution_batch_id',
                        'distribution_batches.id',
                    );
            })
            ->update(['distribution_type' => 'individual']);
    }

    public function down(): void
    {
        Schema::table('distribution_batches', function (Blueprint $table): void {
            $table->dropIndex('distribution_batches_department_type_index');
            $table->dropColumn('distribution_type');
        });

        Schema::table('programs', function (Blueprint $table): void {
            $table->dropColumn('distribution_mode');
        });
    }
};
