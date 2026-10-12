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
        if (Schema::hasTable('disbursement_batches') && ! Schema::hasTable('distribution_batches')) {
            return;
        }

        Schema::whenTableDoesntHaveColumn('distribution_batches', 'venue_details', function (Blueprint $table) {
            $table->text('venue_details')->nullable()->after('venue');
        });

        Schema::whenTableDoesntHaveColumn('distribution_batches', 'venue_latitude', function (Blueprint $table) {
            $table->decimal('venue_latitude', 10, 7)->nullable()->after('venue_details');
        });

        Schema::whenTableDoesntHaveColumn('distribution_batches', 'venue_longitude', function (Blueprint $table) {
            $table->decimal('venue_longitude', 10, 7)->nullable()->after('venue_latitude');
        });

        Schema::whenTableDoesntHaveColumn('distribution_batches', 'opened_at', function (Blueprint $table) {
            $table->timestamp('opened_at')->nullable()->after('approved_at');
        });

        Schema::whenTableDoesntHaveColumn('distribution_batches', 'closed_at', function (Blueprint $table) {
            $table->timestamp('closed_at')->nullable()->after('completed_at');
        });

        Schema::whenTableDoesntHaveColumn('distribution_batches', 'updated_by', function (Blueprint $table) {
            $table->foreignId('updated_by')->nullable()->after('created_by')->constrained('users')->nullOnDelete();
        });

        if (! Schema::hasTable('distribution_batch_assignments')) {
            Schema::create('distribution_batch_assignments', function (Blueprint $table) {
                $table->id();
                $table->foreignId('distribution_batch_id')->constrained()->cascadeOnDelete();
                $table->foreignId('user_id')->constrained()->cascadeOnDelete();
                $table->string('assignment_role');
                $table->foreignId('assigned_by')->constrained('users')->restrictOnDelete();
                $table->timestamps();

                $table->unique(['distribution_batch_id', 'user_id'], 'distribution_batch_assignment_unique');
            });
        }

        if (! Schema::hasTable('distribution_transactions')) {
            Schema::create('distribution_transactions', function (Blueprint $table) {
                $table->id();
                $table->foreignId('distribution_batch_id')->constrained()->cascadeOnDelete();
                $table->foreignId('distribution_batch_item_id')->constrained()->restrictOnDelete();
                $table->string('transaction_number')->unique();
                $table->string('idempotency_key')->unique();
                $table->string('received_by_type')->nullable();
                $table->string('received_by_name')->nullable();
                $table->string('representative_relationship')->nullable();
                $table->text('representative_verification')->nullable();
                $table->string('verification_method')->nullable();
                $table->string('verification_status')->default('pending');
                $table->string('acknowledgment_mode')->nullable();
                $table->text('alternative_reason')->nullable();
                $table->timestamp('privacy_acknowledged_at')->nullable();
                $table->text('camera_waiver_reason')->nullable();
                $table->foreignId('camera_waived_by')->nullable()->constrained('users')->nullOnDelete();
                $table->string('status');
                $table->text('notes')->nullable();
                $table->foreignId('released_by')->nullable()->constrained('users')->nullOnDelete();
                $table->foreignId('witnessed_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamp('captured_at')->nullable();
                $table->timestamp('finalized_at')->nullable();
                $table->timestamp('voided_at')->nullable();
                $table->text('void_reason')->nullable();
                $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
                $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();

                $table->index(['distribution_batch_id', 'status']);
                $table->index(['distribution_batch_item_id', 'status'], 'distribution_tx_item_status_index');
            });
        }

        if (! Schema::hasTable('distribution_evidence')) {
            Schema::create('distribution_evidence', function (Blueprint $table) {
                $table->id();
                $table->foreignId('distribution_transaction_id')->constrained()->cascadeOnDelete();
                $table->string('evidence_type');
                $table->string('disk');
                $table->string('path');
                $table->string('mime_type');
                $table->unsignedBigInteger('file_size');
                $table->char('sha256_hash', 64);
                $table->timestamp('captured_at');
                $table->timestamp('received_at');
                $table->foreignId('captured_by')->constrained('users')->restrictOnDelete();
                $table->json('metadata')->nullable();
                $table->timestamps();

                $table->unique(['distribution_transaction_id', 'evidence_type'], 'distribution_evidence_type_unique');
            });
        }

        if (! Schema::hasTable('distribution_acknowledgment_receipts')) {
            Schema::create('distribution_acknowledgment_receipts', function (Blueprint $table) {
                $table->id();
                $table->foreignId('distribution_transaction_id')
                    ->constrained(indexName: 'distribution_receipt_transaction_fk')
                    ->restrictOnDelete();
                $table->string('receipt_number')->unique();
                $table->string('verification_token', 80)->unique();
                $table->timestamp('issued_at');
                $table->foreignId('issued_by')->constrained('users')->restrictOnDelete();
                $table->unsignedSmallInteger('version')->default(1);
                $table->unsignedBigInteger('supersedes_receipt_id')->nullable();
                $table->string('status');
                $table->unsignedInteger('reprint_count')->default(0);
                $table->text('notes')->nullable();
                $table->json('snapshot');
                $table->timestamps();

                $table->index(['distribution_transaction_id', 'status'], 'distribution_receipt_tx_status_index');
                $table->foreign('supersedes_receipt_id', 'distribution_receipt_supersedes_fk')
                    ->references('id')
                    ->on('distribution_acknowledgment_receipts')
                    ->nullOnDelete();
            });
        } else {
            $this->ensureReceiptConstraints();
        }

        Schema::whenTableDoesntHaveColumn('distribution_release_records', 'distribution_transaction_id', function (Blueprint $table) {
            $table->foreignId('distribution_transaction_id')
                ->nullable()
                ->after('distribution_batch_item_line_id')
                ->constrained(indexName: 'distribution_release_transaction_fk')
                ->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasColumn('distribution_release_records', 'distribution_transaction_id')) {
            Schema::table('distribution_release_records', function (Blueprint $table) {
                $table->dropConstrainedForeignId('distribution_transaction_id');
            });
        }

        Schema::dropIfExists('distribution_acknowledgment_receipts');
        Schema::dropIfExists('distribution_evidence');
        Schema::dropIfExists('distribution_transactions');
        Schema::dropIfExists('distribution_batch_assignments');

        if (Schema::hasColumn('distribution_batches', 'updated_by')) {
            Schema::table('distribution_batches', function (Blueprint $table) {
                $table->dropConstrainedForeignId('updated_by');
            });
        }

        $batchColumns = array_values(array_filter(
            ['venue_details', 'venue_latitude', 'venue_longitude', 'opened_at', 'closed_at'],
            fn (string $column): bool => Schema::hasColumn('distribution_batches', $column),
        ));

        if ($batchColumns !== []) {
            Schema::table('distribution_batches', function (Blueprint $table) use ($batchColumns) {
                $table->dropColumn($batchColumns);
            });
        }
    }

    /**
     * Add receipt indexes and foreign keys when the table already exists without them.
     */
    private function ensureReceiptConstraints(): void
    {
        $tableName = 'distribution_acknowledgment_receipts';

        Schema::whenTableDoesntHaveIndex($tableName, ['receipt_number'], function (Blueprint $table) {
            $table->unique('receipt_number');
        }, 'unique');

        Schema::whenTableDoesntHaveIndex($tableName, ['verification_token'], function (Blueprint $table) {
            $table->unique('verification_token');
        }, 'unique');

        if (! Schema::hasForeignKey($tableName, ['distribution_transaction_id'])) {
            Schema::table($tableName, function (Blueprint $table) {
                $table->foreign('distribution_transaction_id', 'distribution_receipt_transaction_fk')
                    ->references('id')
                    ->on('distribution_transactions')
                    ->restrictOnDelete();
            });
        }

        if (! Schema::hasForeignKey($tableName, ['issued_by'])) {
            Schema::table($tableName, function (Blueprint $table) {
                $table->foreign('issued_by')
                    ->references('id')
                    ->on('users')
                    ->restrictOnDelete();
            });
        }

        if (! Schema::hasForeignKey($tableName, ['supersedes_receipt_id'])) {
            Schema::table($tableName, function (Blueprint $table) {
                $table->foreign('supersedes_receipt_id', 'distribution_receipt_supersedes_fk')
                    ->references('id')
                    ->on('distribution_acknowledgment_receipts')
                    ->nullOnDelete();
            });
        }

        Schema::whenTableDoesntHaveIndex($tableName, ['distribution_transaction_id', 'status'], function (Blueprint $table) {
            $table->index(['distribution_transaction_id', 'status'], 'distribution_receipt_tx_status_index');
        });
    }
};
