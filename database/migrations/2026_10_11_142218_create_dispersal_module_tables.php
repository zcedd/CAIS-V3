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
        Schema::create('dispersal_approval_policies', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->foreignId('department_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('program_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('approval_mode')->nullable();
            $table->string('strategy')->default('sequential');
            $table->boolean('allow_partial_finalization')->default(false);
            $table->boolean('allow_amount_modification')->default(false);
            $table->boolean('allow_self_approval')->default(false);
            $table->boolean('reschedule_requires_reapproval')->default(false);
            $table->boolean('allow_resubmit_after_reject')->default(false);
            $table->boolean('block_active_batch_reuse')->default(true);
            $table->decimal('min_amount', 15, 2)->nullable();
            $table->decimal('max_amount', 15, 2)->nullable();
            $table->boolean('is_active')->default(true);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['department_id', 'is_active']);
            $table->index(['program_id', 'is_active']);
        });

        Schema::create('dispersal_approval_stages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('dispersal_approval_policy_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('sequence');
            $table->string('name');
            $table->string('role_name')->nullable();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamps();

            $table->unique(['dispersal_approval_policy_id', 'sequence'], 'dispersal_stages_policy_sequence_unique');
        });

        Schema::create('dispersal_batches', function (Blueprint $table) {
            $table->id();
            $table->string('batch_code')->unique();
            $table->foreignId('program_id')->constrained()->restrictOnDelete();
            $table->foreignId('department_id')->constrained()->restrictOnDelete();
            $table->foreignId('dispersal_approval_policy_id')->nullable()->constrained()->nullOnDelete();
            $table->string('title');
            $table->text('description')->nullable();
            $table->dateTime('scheduled_at')->nullable();
            $table->string('venue')->nullable();
            $table->string('approval_mode');
            $table->string('status');
            $table->unsignedInteger('revision')->default(1);
            $table->unsignedInteger('lock_version')->default(0);
            $table->string('finalization_scope')->nullable();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('locked_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->text('cancellation_reason')->nullable();
            $table->text('notes')->nullable();
            $table->json('validation_summary')->nullable();
            $table->unsignedInteger('beneficiary_count')->default(0);
            $table->unsignedInteger('approved_beneficiary_count')->default(0);
            $table->decimal('proposed_cash_total', 15, 2)->default(0);
            $table->decimal('approved_cash_total', 15, 2)->default(0);
            $table->timestamps();
            $table->softDeletes();

            $table->index(['department_id', 'status']);
            $table->index(['program_id', 'scheduled_at']);
            $table->index(['status', 'scheduled_at']);
            $table->index('created_by');
        });

        Schema::create('dispersal_batch_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('dispersal_batch_id')->constrained()->cascadeOnDelete();
            $table->foreignId('assistance_id')->constrained()->restrictOnDelete();
            $table->foreignId('beneficiary_id')->constrained()->restrictOnDelete();
            $table->string('approval_status')->default('pending');
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->text('review_remarks')->nullable();
            $table->text('exclusion_reason')->nullable();
            $table->json('warnings')->nullable();
            $table->timestamps();

            $table->unique(['dispersal_batch_id', 'assistance_id'], 'dispersal_batch_assistance_unique');
            $table->index(['dispersal_batch_id', 'approval_status']);
        });

        Schema::create('dispersal_batch_item_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('dispersal_batch_item_id')->constrained()->cascadeOnDelete();
            $table->foreignId('assistance_item_id')->nullable()->constrained('assistance_item')->nullOnDelete();
            $table->foreignId('item_id')->constrained()->restrictOnDelete();
            $table->decimal('proposed_quantity', 15, 2)->nullable();
            $table->decimal('approved_quantity', 15, 2)->nullable();
            $table->decimal('proposed_amount', 15, 2)->nullable();
            $table->decimal('approved_amount', 15, 2)->nullable();
            $table->timestamps();

            $table->index(['dispersal_batch_item_id', 'item_id']);
        });

        Schema::create('dispersal_batch_revisions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('dispersal_batch_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('revision');
            $table->json('snapshot');
            $table->foreignId('submitted_by')->constrained('users')->restrictOnDelete();
            $table->timestamp('created_at')->useCurrent();

            $table->unique(['dispersal_batch_id', 'revision'], 'dispersal_batch_revision_unique');
        });

        Schema::create('dispersal_approval_tasks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('dispersal_batch_id')->constrained()->cascadeOnDelete();
            $table->foreignId('dispersal_approval_stage_id')->constrained()->restrictOnDelete();
            $table->unsignedInteger('revision');
            $table->string('status');
            $table->timestamp('completed_at')->nullable();
            $table->foreignId('completed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(
                ['dispersal_batch_id', 'dispersal_approval_stage_id', 'revision'],
                'dispersal_tasks_batch_stage_revision_unique',
            );
            $table->index(['status', 'revision']);
        });

        Schema::create('dispersal_approval_decisions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('dispersal_batch_id')->constrained()->cascadeOnDelete();
            $table->foreignId('dispersal_batch_item_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('dispersal_approval_task_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('dispersal_approval_stage_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedInteger('revision');
            $table->foreignId('actor_id')->constrained('users')->restrictOnDelete();
            $table->string('previous_decision')->nullable();
            $table->string('decision');
            $table->decimal('previous_amount', 15, 2)->nullable();
            $table->decimal('new_amount', 15, 2)->nullable();
            $table->decimal('previous_quantity', 15, 2)->nullable();
            $table->decimal('new_quantity', 15, 2)->nullable();
            $table->text('remarks')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['dispersal_batch_id', 'revision']);
            $table->index('actor_id');
        });

        Schema::create('dispersal_release_records', function (Blueprint $table) {
            $table->id();
            $table->foreignId('dispersal_batch_item_id')->constrained()->cascadeOnDelete();
            $table->foreignId('dispersal_batch_item_line_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('assistance_item_id')->nullable()->constrained('assistance_item')->nullOnDelete();
            $table->string('outcome');
            $table->decimal('actual_quantity', 15, 2)->nullable();
            $table->decimal('actual_amount', 15, 2)->nullable();
            $table->dateTime('released_at');
            $table->foreignId('released_by')->constrained('users')->restrictOnDelete();
            $table->string('receipt_reference')->nullable();
            $table->boolean('identity_verified')->default(false);
            $table->text('remarks')->nullable();
            $table->timestamps();

            $table->index(['dispersal_batch_item_id', 'outcome']);
            $table->unique(
                ['dispersal_batch_item_line_id', 'receipt_reference'],
                'dispersal_release_line_receipt_unique',
            );
        });

        Schema::create('dispersal_batch_documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('dispersal_batch_id')->constrained()->cascadeOnDelete();
            $table->foreignId('document_type_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('uploaded_by')->constrained('users')->restrictOnDelete();
            $table->string('original_name');
            $table->string('disk')->default('local');
            $table->string('path');
            $table->string('mime_type')->nullable();
            $table->unsignedBigInteger('size')->default(0);
            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('dispersal_batch_documents');
        Schema::dropIfExists('dispersal_release_records');
        Schema::dropIfExists('dispersal_approval_decisions');
        Schema::dropIfExists('dispersal_approval_tasks');
        Schema::dropIfExists('dispersal_batch_revisions');
        Schema::dropIfExists('dispersal_batch_item_lines');
        Schema::dropIfExists('dispersal_batch_items');
        Schema::dropIfExists('dispersal_batches');
        Schema::dropIfExists('dispersal_approval_stages');
        Schema::dropIfExists('dispersal_approval_policies');
    }
};
