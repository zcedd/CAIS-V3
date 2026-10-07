<?php

namespace Database\Seeders;

use App\Models\Department;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Seeder;

class DepartmentSlugSeeder extends Seeder
{
    /**
     * Fill missing department slugs from their names.
     */
    public function run(): void
    {
        Department::query()
            ->withTrashed()
            ->where(function (Builder $query): void {
                $query->whereNull('slug')->orWhere('slug', '');
            })
            ->orderBy('id')
            ->each(function (Department $department): void {
                $department->slug = Department::uniqueSlugFromName($department->name, $department->id);
                $department->save();
            });
    }
}
