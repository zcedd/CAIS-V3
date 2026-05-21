<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
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
        return DB::table('information_schema.KEY_COLUMN_USAGE')
            ->whereRaw('TABLE_SCHEMA = DATABASE()')
            ->where('TABLE_NAME', $table)
            ->where('COLUMN_NAME', $column)
            ->where('REFERENCED_TABLE_NAME', $referencedTable)
            ->exists();
    }

    private function dropColumnForeignKeyOrIndex(string $table, string $column): void
    {
        $foreignKeys = DB::table('information_schema.KEY_COLUMN_USAGE as usage')
            ->join('information_schema.TABLE_CONSTRAINTS as constraints', function ($join): void {
                $join->on('usage.CONSTRAINT_NAME', '=', 'constraints.CONSTRAINT_NAME')
                    ->on('usage.TABLE_SCHEMA', '=', 'constraints.CONSTRAINT_SCHEMA')
                    ->on('usage.TABLE_NAME', '=', 'constraints.TABLE_NAME');
            })
            ->whereRaw('usage.TABLE_SCHEMA = DATABASE()')
            ->where('usage.TABLE_NAME', $table)
            ->where('usage.COLUMN_NAME', $column)
            ->where('constraints.CONSTRAINT_TYPE', 'FOREIGN KEY')
            ->pluck('usage.CONSTRAINT_NAME');

        foreach ($foreignKeys as $foreignKey) {
            Schema::table($table, function (Blueprint $table) use ($foreignKey) {
                $table->dropForeign($foreignKey);
            });
        }

        $indexes = DB::table('information_schema.STATISTICS')
            ->whereRaw('TABLE_SCHEMA = DATABASE()')
            ->where('TABLE_NAME', $table)
            ->where('COLUMN_NAME', $column)
            ->where('INDEX_NAME', '!=', 'PRIMARY')
            ->distinct()
            ->pluck('INDEX_NAME');

        foreach ($indexes as $index) {
            Schema::table($table, function (Blueprint $table) use ($index) {
                $table->dropIndex($index);
            });
        }
    }
};
