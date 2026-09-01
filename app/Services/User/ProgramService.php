<?php

namespace App\Services\User;

use App\Models\Department;
use App\Models\Fund;
use App\Models\Item;
use App\Models\Program;
use App\Models\ProgramEligibilityRule;
use App\Models\ProgramItemCap;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Carbon;

class ProgramService
{
    private const PROGRAMS_PER_PAGE = 12;

    public function __construct(
        private ItemService $itemService,
        private DashboardService $dashboardService,
        private ProgramFieldService $programFieldService,
    ) {}

    /**
     * @param  list<string>  $types
     * @param  list<string>  $statuses
     */
    public function paginateForDepartment(
        Department $department,
        string $search,
        array $types,
        array $statuses,
        int $perPage = self::PROGRAMS_PER_PAGE,
    ): LengthAwarePaginator {
        return Program::query()
            ->select([
                'id',
                'name',
                'descriptions',
                'start_at',
                'end_at',
                'is_closed',
                'is_organization',
                'department_id',
            ])
            ->with(['department:id,name,slug'])
            ->where('department_id', $department->id)
            ->when($search !== '', fn ($query) => $query->where('name', 'like', '%'.$search.'%'))
            ->when(
                count($types) === 1 && in_array('individual', $types, true),
                fn ($query) => $query->where('is_organization', false),
            )
            ->when(
                count($types) === 1 && in_array('organization', $types, true),
                fn ($query) => $query->where('is_organization', true),
            )
            ->when(
                count($statuses) === 1 && in_array('open', $statuses, true),
                fn ($query) => $query->where('is_closed', false),
            )
            ->when(
                count($statuses) === 1 && in_array('closed', $statuses, true),
                fn ($query) => $query->where('is_closed', true),
            )
            ->orderByDesc('id')
            ->paginate($perPage)
            ->withQueryString();
    }

    /**
     * @param  array<string, mixed>  $validated
     */
    public function create(Department $department, array $validated): Program
    {
        $program = Program::query()->create([
            'name' => $validated['name'],
            'descriptions' => $validated['descriptions'],
            'start_at' => $validated['start_at'],
            'end_at' => $validated['end_at'] ?? null,
            'department_id' => $department->id,
            'is_closed' => false,
            'is_organization' => $validated['is_organization'] ?? false,
        ]);

        $program->fund()->attach($validated['fund_ids']);
        $program->item()->attach($validated['item_ids']);

        if (array_key_exists('fields', $validated)) {
            $this->programFieldService->syncForProgram($program, $validated['fields'] ?? []);
        }

        $this->syncEligibility($program, $validated);

        return $program;
    }

    /**
     * @param  array<string, mixed>  $validated
     */
    public function update(Program $program, array $validated): void
    {
        $program->update([
            'name' => $validated['name'],
            'descriptions' => $validated['descriptions'],
            'start_at' => $validated['start_at'],
            'end_at' => $validated['end_at'] ?? null,
            'is_organization' => $validated['is_organization'] ?? false,
            'is_closed' => $validated['is_closed'] ?? false,
        ]);

        $program->fund()->sync($validated['fund_ids']);
        $program->item()->sync($validated['item_ids']);

        if (array_key_exists('fields', $validated)) {
            $this->programFieldService->syncForProgram($program, $validated['fields'] ?? []);
        }

        $this->syncEligibility($program, $validated);
    }

    /**
     * @return array{
     *     total_requests: int,
     *     delivered_requests: int,
     *     in_progress_requests: int,
     *     total_delivered_items: int
     * }
     */
    public function summary(Program $program): array
    {
        return $this->dashboardService->summaryForProgram($program);
    }

    /**
     * @return list<array{status: string, count: int}>
     */
    public function statusBreakdown(Program $program): array
    {
        $program->loadMissing('department:id,name,slug');

        $department = $program->department
            ?? Department::query()->findOrFail($program->department_id);

        return $this->dashboardService->requestStatusChart(
            $department,
            ['program' => [$program->id]],
        );
    }

    /**
     * @return list<array{id: int, name: string, year: string|null, amount: float|null}>
     */
    public function programFundsForDisplay(Program $program): array
    {
        return $program->fund()
            ->orderBy('name')
            ->get(['funds.id', 'funds.name', 'funds.year', 'funds.amount'])
            ->map(static fn (Fund $fund): array => [
                'id' => $fund->id,
                'name' => $fund->name,
                'year' => $fund->year !== null ? (string) $fund->year : null,
                'amount' => $fund->amount !== null ? (float) $fund->amount : null,
            ])
            ->values()
            ->all();
    }

    /**
     * @return array<string, mixed>
     */
    public function showPayload(Program $program): array
    {
        return $this->showOverviewPayload($program);
    }

    /**
     * @return array<string, mixed>
     */
    public function showOverviewPayload(Program $program): array
    {
        return [
            ...$program->only([
                'id',
                'name',
                'descriptions',
                'start_at',
                'end_at',
                'is_closed',
                'is_organization',
                'department_id',
            ]),
            'start_at_input' => $this->programDateForInput($program->getRawOriginal('start_at')),
            'end_at_input' => $this->programDateForInput($program->getRawOriginal('end_at')),
        ];
    }

    /**
     * @return array{
     *     fund_ids: list<int>,
     *     item_ids: list<int>,
     *     fields: list<array{
     *         id: int,
     *         label: string,
     *         key: string,
     *         type: string,
     *         options: list<string>|null,
     *         is_required: bool,
     *         show_in_table: bool,
     *         sort_order: int
     *     }>
     * }
     */
    public function editRelationsPayload(Program $program): array
    {
        $program->loadMissing(['fund:id', 'item:id']);

        return [
            'fund_ids' => $program->fund->pluck('id')->values()->all(),
            'item_ids' => $program->item->pluck('id')->values()->all(),
            'fields' => $this->programFieldService->fieldsPayload($program),
            'eligibility' => $this->eligibilityPayload($program),
        ];
    }

    /**
     * @return list<array{
     *     id: int,
     *     label: string,
     *     key: string,
     *     type: string,
     *     options: list<string>|null,
     *     is_required: bool,
     *     show_in_table: bool,
     *     sort_order: int
     * }>
     */
    public function programFieldsForForms(Program $program): array
    {
        return $this->programFieldService->fieldsPayload($program);
    }

    /**
     * @return list<array{id: int, name: string, year: string}>
     */
    public function departmentFundsForSelect(Department $department): array
    {
        return Fund::query()
            ->where('department_id', $department->id)
            ->orderBy('name')
            ->get(['id', 'name', 'year'])
            ->all();
    }

    /**
     * @return list<array{id: int, name: string, unit: string|null}>
     */
    public function departmentItemsForSelect(Department $department): array
    {
        return $this->itemService->departmentItemsForSelect($department);
    }

    /**
     * @return list<array{id: int, name: string}>
     */
    public function transferProgramsForSelect(Department $department, Program $program): array
    {
        return Program::query()
            ->where('department_id', $department->id)
            ->whereKeyNot($program->id)
            ->where('is_closed', false)
            ->where('is_organization', $program->is_organization)
            ->orderBy('name')
            ->get(['id', 'name'])
            ->map(static fn (Program $candidate): array => [
                'id' => $candidate->id,
                'name' => $candidate->name,
            ])
            ->values()
            ->all();
    }

    /**
     * @return list<array{id: int, name: string, unit: string|null}>
     */
    public function programItemsForSelect(Program $program): array
    {
        $itemIds = $program->item()->pluck('items.id');

        if ($itemIds->isEmpty()) {
            return [];
        }

        return Item::query()
            ->whereIn('id', $itemIds)
            ->orderBy('name')
            ->with('unitMeasurement:id,name')
            ->get(['id', 'name', 'item_unit_measurement_id'])
            ->map(static fn (Item $item): array => [
                'id' => $item->id,
                'name' => $item->name,
                'unit' => $item->unitMeasurement?->name,
            ])
            ->values()
            ->all();
    }

    /**
     * @param  array<string, mixed>  $validated
     */
    private function syncEligibility(Program $program, array $validated): void
    {
        $isOrganization = (bool) ($validated['is_organization'] ?? $program->is_organization);

        $program->eligibilityRule()->updateOrCreate([], [
            'cooldown_days' => $validated['cooldown_days'] ?? null,
            'require_pwd' => $isOrganization ? false : (bool) ($validated['require_pwd'] ?? false),
            'require_4ps' => $isOrganization ? false : (bool) ($validated['require_4ps'] ?? false),
            'require_solo_parent' => $isOrganization ? false : (bool) ($validated['require_solo_parent'] ?? false),
            'require_indigenous' => $isOrganization ? false : (bool) ($validated['require_indigenous'] ?? false),
        ]);

        $itemIds = collect($validated['item_ids'] ?? [])
            ->map(static fn (mixed $id): int => (int) $id)
            ->filter()
            ->values();

        $caps = collect($validated['item_caps'] ?? [])
            ->filter(static fn (mixed $row): bool => is_array($row))
            ->map(static fn (array $row): array => [
                'item_id' => (int) ($row['item_id'] ?? 0),
                'max_released_per_year' => (int) ($row['max_released_per_year'] ?? 0),
            ])
            ->filter(static fn (array $row): bool => $row['item_id'] > 0
                && $row['max_released_per_year'] > 0
                && $itemIds->contains($row['item_id']))
            ->unique('item_id')
            ->values();

        $keptItemIds = $caps->pluck('item_id')->all();

        if ($keptItemIds === []) {
            $program->itemCaps()->delete();
        } else {
            $program->itemCaps()->whereNotIn('item_id', $keptItemIds)->delete();
        }

        foreach ($caps as $cap) {
            $program->itemCaps()->updateOrCreate(
                ['item_id' => $cap['item_id']],
                ['max_released_per_year' => $cap['max_released_per_year']],
            );
        }
    }

    /**
     * @return array{
     *     cooldown_days: int|null,
     *     require_pwd: bool,
     *     require_4ps: bool,
     *     require_solo_parent: bool,
     *     require_indigenous: bool,
     *     item_caps: list<array{item_id: int, max_released_per_year: int}>
     * }
     */
    public function eligibilityPayload(Program $program): array
    {
        $program->loadMissing(['eligibilityRule', 'itemCaps']);

        $rule = $program->eligibilityRule;

        return [
            'cooldown_days' => $rule instanceof ProgramEligibilityRule ? $rule->cooldown_days : null,
            'require_pwd' => $rule instanceof ProgramEligibilityRule ? $rule->require_pwd : false,
            'require_4ps' => $rule instanceof ProgramEligibilityRule ? $rule->require_4ps : false,
            'require_solo_parent' => $rule instanceof ProgramEligibilityRule ? $rule->require_solo_parent : false,
            'require_indigenous' => $rule instanceof ProgramEligibilityRule ? $rule->require_indigenous : false,
            'item_caps' => $program->itemCaps
                ->map(static fn (ProgramItemCap $cap): array => [
                    'item_id' => $cap->item_id,
                    'max_released_per_year' => $cap->max_released_per_year,
                ])
                ->values()
                ->all(),
        ];
    }

    private function programDateForInput(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        return Carbon::parse($value)->toDateString();
    }
}
