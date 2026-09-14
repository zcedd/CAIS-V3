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
        Schema::create('workflows', function (Blueprint $table) {
            $table->id();
            $table->foreignId('department_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('template')->default('standard');
            $table->boolean('is_default')->default(false);
            $table->foreignId('staff_entry_request_status_id')->constrained('request_statuses')->restrictOnDelete();
            $table->foreignId('public_entry_request_status_id')->constrained('request_statuses')->restrictOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['department_id', 'is_default']);
        });

        Schema::create('workflow_steps', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workflow_id')->constrained()->cascadeOnDelete();
            $table->foreignId('request_status_id')->constrained('request_statuses')->restrictOnDelete();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->foreignId('default_request_sub_status_id')->nullable()->constrained('request_sub_statuses')->nullOnDelete();
            $table->unsignedSmallInteger('sla_hours')->nullable();
            $table->boolean('requires_assignee')->default(false);
            $table->string('permission')->nullable();
            $table->boolean('allows_skip_to_deliver')->default(false);
            $table->timestamps();

            $table->unique(['workflow_id', 'request_status_id'], 'workflow_steps_status_unique');
        });

        Schema::create('workflow_step_transitions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workflow_step_id')->constrained('workflow_steps')->cascadeOnDelete();
            $table->foreignId('to_request_status_id')->constrained('request_statuses')->restrictOnDelete();
            $table->timestamps();

            $table->unique(['workflow_step_id', 'to_request_status_id'], 'workflow_step_to_status_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('workflow_step_transitions');
        Schema::dropIfExists('workflow_steps');
        Schema::dropIfExists('workflows');
    }
};
