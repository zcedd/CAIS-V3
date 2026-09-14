<?php

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
        Schema::table('assistances', function (Blueprint $table) {
            $table->foreignId('assigned_to_id')->nullable()->after('user_id')->constrained('users')->nullOnDelete();
            $table->timestamp('assigned_at')->nullable()->after('assigned_to_id');
            $table->timestamp('sla_due_at')->nullable()->after('assigned_at');
            $table->timestamp('sla_paused_at')->nullable()->after('sla_due_at');
            $table->index(['assigned_to_id', 'current_request_sub_status_id']);
            $table->index('sla_due_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('assistances', function (Blueprint $table) {
            $table->dropIndex(['assigned_to_id', 'current_request_sub_status_id']);
            $table->dropIndex(['sla_due_at']);
            $table->dropConstrainedForeignId('assigned_to_id');
            $table->dropColumn(['assigned_at', 'sla_due_at', 'sla_paused_at']);
        });
    }
};
