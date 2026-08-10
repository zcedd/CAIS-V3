<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The morph column `beneficiary_id` replaced these legacy columns; nothing reads
     * or writes them anymore, so drop them along with their constraints.
     */
    public function up(): void
    {
        $columns = collect(['individual_id', 'organization_id'])
            ->filter(static fn (string $column): bool => Schema::hasColumn('assistances', $column))
            ->values()
            ->all();

        if ($columns === []) {
            return;
        }

        foreach ($columns as $column) {
            $this->dropForeignKeysForColumn('assistances', $column);
        }

        Schema::table('assistances', function (Blueprint $table) use ($columns) {
            $table->dropColumn($columns);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('assistances', function (Blueprint $table) {
            if (! Schema::hasColumn('assistances', 'individual_id')) {
                $table->foreignId('individual_id')
                    ->nullable()
                    ->after('beneficiary_id')
                    ->constrained('individuals')
                    ->cascadeOnDelete();
            }

            if (! Schema::hasColumn('assistances', 'organization_id')) {
                $table->foreignId('organization_id')
                    ->nullable()
                    ->after('individual_id')
                    ->constrained('organizations');
            }
        });
    }

    private function dropForeignKeysForColumn(string $table, string $column): void
    {
        $foreignKeys = Schema::getForeignKeys($table);

        foreach ($foreignKeys as $foreignKey) {
            if ($foreignKey['name'] === null || ! in_array($column, $foreignKey['columns'], true)) {
                continue;
            }

            Schema::table($table, function (Blueprint $tableBlueprint) use ($foreignKey) {
                $tableBlueprint->dropForeign($foreignKey['name']);
            });
        }

        // SQLite may not report the FK name in a droppable form; dropping by column
        // list forces a table rebuild without the constraint before dropColumn runs.
        if (Schema::hasColumn($table, $column)) {
            try {
                Schema::table($table, function (Blueprint $tableBlueprint) use ($column) {
                    $tableBlueprint->dropForeign([$column]);
                });
            } catch (Throwable) {
                // Foreign key already removed or never existed under this name.
            }
        }
    }
};
