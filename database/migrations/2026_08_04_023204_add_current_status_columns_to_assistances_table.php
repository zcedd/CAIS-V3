<?php

use App\Actions\User\SyncAssistanceCurrentStatus;
use App\Models\Assistance;
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
            if (! Schema::hasColumn('assistances', 'current_request_sub_status_id')) {
                $table->foreignId('current_request_sub_status_id')
                    ->nullable()
                    ->after('mode_of_request_id')
                    ->constrained('request_sub_statuses')
                    ->nullOnDelete();
            }

            if (! Schema::hasColumn('assistances', 'current_status_recorded_at')) {
                $table->timestamp('current_status_recorded_at')
                    ->nullable()
                    ->after('current_request_sub_status_id');
            }

            if (! Schema::hasColumn('assistances', 'was_delivered')) {
                $table->boolean('was_delivered')
                    ->default(false)
                    ->after('current_status_recorded_at');
            }
        });

        if (! $this->hasIndex('assistances', 'assistances_was_delivered_index')) {
            Schema::table('assistances', function (Blueprint $table) {
                $table->index('was_delivered', 'assistances_was_delivered_index');
            });
        }

        $this->backfillDenormalizedStatus();
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if ($this->hasIndex('assistances', 'assistances_was_delivered_index')) {
            Schema::table('assistances', function (Blueprint $table) {
                $table->dropIndex('assistances_was_delivered_index');
            });
        }

        if (Schema::hasColumn('assistances', 'current_request_sub_status_id')) {
            foreach (Schema::getForeignKeys('assistances') as $foreignKey) {
                if ($foreignKey['name'] !== null && in_array('current_request_sub_status_id', $foreignKey['columns'], true)) {
                    Schema::table('assistances', function (Blueprint $table) use ($foreignKey) {
                        $table->dropForeign($foreignKey['name']);
                    });
                }
            }
        }

        Schema::table('assistances', function (Blueprint $table) {
            foreach (['current_request_sub_status_id', 'current_status_recorded_at', 'was_delivered'] as $column) {
                if (Schema::hasColumn('assistances', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }

    /**
     * Recompute denormalized columns from status history via the same action
     * used at runtime so MySQL and SQLite stay consistent.
     */
    private function backfillDenormalizedStatus(): void
    {
        $sync = app(SyncAssistanceCurrentStatus::class);

        Assistance::query()
            ->withTrashed()
            ->orderBy('id')
            ->chunkById(200, function ($assistances) use ($sync): void {
                foreach ($assistances as $assistance) {
                    $sync($assistance);
                }
            });
    }

    private function hasIndex(string $table, string $indexName): bool
    {
        return collect(Schema::getIndexes($table))->contains(
            static fn (array $index): bool => ($index['name'] ?? null) === $indexName,
        );
    }
};
