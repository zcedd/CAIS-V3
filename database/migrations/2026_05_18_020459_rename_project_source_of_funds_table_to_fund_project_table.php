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

        if (Schema::hasColumn($table, 'source_of_fund_id')) {
            $this->dropColumnForeignKeyOrIndex($table, 'source_of_fund_id');

            Schema::table($table, function (Blueprint $table) {
                $table->renameColumn('source_of_fund_id', 'fund_id');
            });
        }

        if (Schema::hasColumn($table, 'fund_id') && ! $this->foreignKeyExists($table, 'fund_id', 'funds')) {
            Schema::table($table, function (Blueprint $table) {
                $table->foreign('fund_id')
                    ->references('id')
                    ->on('funds')
                    ->onDelete('cascade');
            });
        }

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

        if (Schema::hasTable('project_source_of_fund')) {
            Schema::rename('project_source_of_fund', 'fund_program');
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('fund_program')) {
            Schema::rename('fund_program', 'project_source_of_fund');
        }

        $table = $this->pivotTableName();

        if ($this->foreignKeyExists($table, 'fund_id', 'funds')) {
            Schema::table($table, function (Blueprint $table) {
                $table->dropForeign(['fund_id']);
            });
        }

        if (Schema::hasColumn($table, 'fund_id')) {
            Schema::table($table, function (Blueprint $table) {
                $table->renameColumn('fund_id', 'source_of_fund_id');
            });
        }

        if (Schema::hasColumn($table, 'source_of_fund_id') && ! $this->foreignKeyExists($table, 'source_of_fund_id', 'funds')) {
            Schema::table($table, function (Blueprint $table) {
                $table->foreign('source_of_fund_id')
                    ->references('id')
                    ->on('funds')
                    ->onDelete('cascade');
            });
        }

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
        if (Schema::hasTable('fund_program')) {
            return 'fund_program';
        }

        return 'project_source_of_fund';
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
