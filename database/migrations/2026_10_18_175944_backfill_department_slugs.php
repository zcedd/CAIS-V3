<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

return new class extends Migration
{
    /**
     * Fill missing department slugs from the department name.
     *
     * Existing slugs, including short codes such as ito and treasury, are left unchanged.
     */
    public function up(): void
    {
        $departments = DB::table('departments')
            ->where(function ($query): void {
                $query->whereNull('slug')->orWhere('slug', '');
            })
            ->orderBy('id')
            ->get(['id', 'name']);

        foreach ($departments as $department) {
            DB::table('departments')
                ->where('id', $department->id)
                ->update([
                    'slug' => $this->uniqueSlug((string) $department->name, (int) $department->id),
                ]);
        }
    }

    /**
     * Slugs filled here cannot be separated from slugs that were already stored.
     */
    public function down(): void
    {
        //
    }

    private function uniqueSlug(string $name, int $ignoreId): string
    {
        $base = Str::slug($name) !== '' ? Str::slug($name) : 'department';
        $slug = $base;
        $suffix = 2;

        while (DB::table('departments')->where('slug', $slug)->where('id', '!=', $ignoreId)->exists()) {
            $slug = $base.'-'.$suffix;
            $suffix++;
        }

        return $slug;
    }
};
