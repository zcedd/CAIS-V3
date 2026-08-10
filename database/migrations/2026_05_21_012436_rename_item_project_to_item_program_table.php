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
        $table = $this->pivotTableName();

        if (Schema::hasColumn($table, 'project_id')) {
            $this->dropColumnForeignKeyOrIndex($table, 'project_id');

            Schema::table($table, function (Blueprint $table) {
                $table->renameColumn('project_id', 'program_id');
            });
        }

        if (Schema::hasColumn($table, 'program_id') && ! $this->foreignKeyExists($table, 'program_id', 'programs')) {
            Schema::table($table, function (Blueprint $table) {
                $table->foreign('program_id')
                    ->references('id')
                    ->on('programs')
                    ->onDelete('cascade');
            });
        }

        if (Schema::hasTable('item_project')) {
            Schema::rename('item_project', 'item_program');
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('item_program')) {
            Schema::rename('item_program', 'item_project');
        }

        $table = $this->pivotTableName();

        if ($this->foreignKeyExists($table, 'program_id', 'programs')) {
            Schema::table($table, function (Blueprint $table) {
                $table->dropForeign(['program_id']);
            });
        }

        if (Schema::hasColumn($table, 'program_id')) {
            Schema::table($table, function (Blueprint $table) {
                $table->renameColumn('program_id', 'project_id');
            });
        }

        if (Schema::hasColumn($table, 'project_id') && ! $this->foreignKeyExists($table, 'project_id', 'programs')) {
            Schema::table($table, function (Blueprint $table) {
                $table->foreign('project_id')
                    ->references('id')
                    ->on('programs')
                    ->onDelete('cascade');
            });
        }
    }

    private function pivotTableName(): string
    {
        if (Schema::hasTable('item_program')) {
            return 'item_program';
        }

        return 'item_project';
    }

    private function foreignKeyExists(string $table, string $column, string $referencedTable): bool
    {
        return collect(Schema::getForeignKeys($table))->contains(
            static fn (array $foreignKey): bool => in_array($column, $foreignKey['columns'], true)
                && $foreignKey['foreign_table'] === $referencedTable,
        );
    }

    private function dropColumnForeignKeyOrIndex(string $table, string $column): void
    {
        foreach (Schema::getForeignKeys($table) as $foreignKey) {
            if ($foreignKey['name'] !== null && in_array($column, $foreignKey['columns'], true)) {
                Schema::table($table, function (Blueprint $tableBlueprint) use ($foreignKey) {
                    $tableBlueprint->dropForeign($foreignKey['name']);
                });
            }
        }

        foreach (Schema::getIndexes($table) as $index) {
            if (! $index['primary'] && in_array($column, $index['columns'], true)) {
                Schema::table($table, function (Blueprint $tableBlueprint) use ($index) {
                    $tableBlueprint->dropIndex($index['name']);
                });
            }
        }
    }
};
