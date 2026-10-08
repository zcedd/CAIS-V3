<?php

namespace App\Services\User;

use App\Actions\User\CreateProgramBatch;
use App\Enums\ProgramApprovalStatus;
use App\Enums\ProgramKind;
use App\Models\Department;
use App\Models\Fund;
use App\Models\Item;
use App\Models\Program;
use App\Models\ProgramEligibilityRule;
use App\Models\ProgramItemCap;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Gate;

class ProgramService
{
    private const PROGRAMS_PER_PAGE = 12;

    public function __construct(
        private ItemService $itemService,
        private DashboardService $dashboardService,
        private ProgramFieldService $programFieldService,
        private ProgramDocumentRequirementService $programDocumentRequirementService,
        private StockLedgerService $stockLedgerService,
        private CreateProgramBatch $createProgramBatch,
        private WorkflowService $workflowService,
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
                'public_intake',
                'department_id',
                'kind',
            ])
            ->with(['department:id,name,slug'])
            ->withCount([
                'batches',
                'batches as open_batches_count' => fn ($query) => $query->where('is_closed', false),
            ])
            ->roots()
            ->where('department_id', $department->id)
            ->when($search !== '', function ($query) use ($search): void {
                $query->where(function ($searchQuery) use ($search): void {
                    $searchQuery
                        ->where('name', 'like', '%'.$search.'%')
                        ->orWhereHas('batches', function ($batchQuery) use ($search): void {
                            $batchQuery
                                ->where('name', 'like', '%'.$search.'%')
                                ->orWhere('batch_name', 'like', '%'.$search.'%');
                        });
                });
            })
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
        $kind = ProgramKind::tryFrom((string) ($validated['kind'] ?? ProgramKind::Standalone->value))
            ?? ProgramKind::Standalone;

        $program = Program::query()->create([
            'name' => $validated['name'],
            'descriptions' => $validated['descriptions'],
            'start_at' => $validated['start_at'],
            'end_at' => $validated['end_at'] ?? null,
            'department_id' => $department->id,
            'is_closed' => false,
            'is_organization' => $validated['is_organization'] ?? false,
            'public_intake' => $kind === ProgramKind::Scheme
                ? false
                : (bool) ($validated['public_intake'] ?? false),
            'kind' => $kind,
            'approval_status' => ProgramApprovalStatus::Draft,
            'workflow_id' => $validated['workflow_id'] ?? null,
        ]);

        if ($kind !== ProgramKind::Scheme) {
            $program->fund()->attach($validated['fund_ids'] ?? []);
        }

        $program->item()->attach($validated['item_ids']);

        if (array_key_exists('fields', $validated)) {
            $this->programFieldService->syncForProgram($program, $validated['fields'] ?? []);
        }

        if (array_key_exists('document_requirements', $validated)) {
            $this->programDocumentRequirementService->syncForProgram(
                $program,
                $validated['document_requirements'] ?? [],
            );
        }

        $this->syncEligibility($program, $validated);

        if ($kind === ProgramKind::Scheme && isset($validated['first_batch']) && is_array($validated['first_batch'])) {
            ($this->createProgramBatch)($program, $validated['first_batch']);
        }

        $this->forgetDashboardFilterOptions($department->id);

        return $program->refresh();
    }

    /**
     * @param  array<string, mixed>  $validated
     */
    public function update(Program $program, array $validated): void
    {
        if ($program->isBatch()) {
            $this->updateBatch($program, $validated);

            return;
        }

        if ($program->isScheme()) {
            $this->updateScheme($program, $validated);

            return;
        }

        $payload = [
            'name' => $validated['name'],
            'descriptions' => $validated['descriptions'],
            'start_at' => $validated['start_at'],
            'end_at' => $validated['end_at'] ?? null,
            'is_organization' => $validated['is_organization'] ?? false,
            'is_closed' => $validated['is_closed'] ?? false,
            'public_intake' => $validated['public_intake'] ?? false,
        ];

        if (array_key_exists('workflow_id', $validated)) {
            $payload['workflow_id'] = $validated['workflow_id'];
        }

        $program->update($payload);

        $program->fund()->sync($validated['fund_ids']);
        $program->item()->sync($validated['item_ids']);

        if (array_key_exists('fields', $validated)) {
            $this->programFieldService->syncForProgram($program, $validated['fields'] ?? []);
        }

        if (array_key_exists('document_requirements', $validated)) {
            $this->programDocumentRequirementService->syncForProgram(
                $program,
                $validated['document_requirements'] ?? [],
            );
        }

        $this->syncEligibility($program, $validated);
        $this->forgetDashboardFilterOptions($program->department_id);
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
     * @return list<array{id: int, name: string, is_default: bool}>
     */
    public function workflowOptions(Department $department): array
    {
        return $this->workflowService->optionsForDepartment($department);
    }

    /**
     * @return array<string, mixed>
     */
    public function showOverviewPayload(Program $program): array
    {
        $program->loadMissing('parent:id,name');

        $parent = $program->parent;

        return [
            ...$program->only([
                'id',
                'name',
                'descriptions',
                'start_at',
                'end_at',
                'is_closed',
                'is_organization',
                'public_intake',
                'department_id',
                'kind',
                'batch_number',
                'batch_name',
                'workflow_id',
            ]),
            'start_at_input' => $this->programDateForInput($program->getRawOriginal('start_at')),
            'end_at_input' => $this->programDateForInput($program->getRawOriginal('end_at')),
            'approval_status' => $program->approval_status instanceof ProgramApprovalStatus
                ? $program->approval_status->value
                : ProgramApprovalStatus::Approved->value,
            'approval_label' => $program->approval_status instanceof ProgramApprovalStatus
                ? $program->approval_status->label()
                : ProgramApprovalStatus::Approved->label(),
            'parent' => $parent instanceof Program
                ? [
                    'id' => $parent->id,
                    'name' => $parent->name,
                ]
                : null,
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
     *     }>,
     *     document_requirements: list<array{
     *         id: int,
     *         document_type_id: int,
     *         is_required: bool,
     *         required_before: string,
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
            'document_requirements' => $this->programDocumentRequirementService->requirementsPayload($program),
            'eligibility' => $this->eligibilityPayload($program),
            'workflow_id' => $program->workflow_id,
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
     * @return list<array{id: int, name: string, unit: string|null, kind: string}>
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
            ->transferTargetsFor($program)
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
     * @return list<array{id: int, name: string, unit: string|null, kind: string, remaining: int|null}>
     */
    public function programItemsForSelect(Program $program): array
    {
        $itemIds = $program->item()->pluck('items.id');

        if ($itemIds->isEmpty()) {
            return [];
        }

        $remaining = $this->stockLedgerService->remainingByItemId($program);

        return Item::query()
            ->whereIn('id', $itemIds)
            ->orderBy('name')
            ->with('unitMeasurement:id,name')
            ->get(['id', 'name', 'kind', 'item_unit_measurement_id'])
            ->map(static fn (Item $item): array => [
                'id' => $item->id,
                'name' => $item->name,
                'unit' => $item->unitMeasurement?->name,
                'kind' => $item->kind,
                'remaining' => $item->tracksInventory()
                    ? ($remaining[$item->id] ?? 0)
                    : null,
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
        $source = $program->eligibilityProgram();
        $source->loadMissing(['eligibilityRule', 'itemCaps']);

        $rule = $source->eligibilityRule;

        return [
            'cooldown_days' => $rule instanceof ProgramEligibilityRule ? $rule->cooldown_days : null,
            'require_pwd' => $rule instanceof ProgramEligibilityRule ? $rule->require_pwd : false,
            'require_4ps' => $rule instanceof ProgramEligibilityRule ? $rule->require_4ps : false,
            'require_solo_parent' => $rule instanceof ProgramEligibilityRule ? $rule->require_solo_parent : false,
            'require_indigenous' => $rule instanceof ProgramEligibilityRule ? $rule->require_indigenous : false,
            'item_caps' => $source->itemCaps
                ->map(static fn (ProgramItemCap $cap): array => [
                    'item_id' => $cap->item_id,
                    'max_released_per_year' => $cap->max_released_per_year,
                ])
                ->values()
                ->all(),
        ];
    }

    /**
     * @return list<array{
     *     id: int,
     *     name: string,
     *     batch_name: string|null,
     *     batch_number: int|null,
     *     start_at: mixed,
     *     end_at: mixed,
     *     is_closed: bool,
     *     public_intake: bool,
     *     total_requests: int,
     *     approval_status: string,
     *     approval_label: string,
     *     can_submit_approval: bool
     * }>
     */
    public function schemeBatchesPayload(Program $scheme): array
    {
        $scheme->loadMissing('batches');

        $requestCounts = $scheme->batches
            ->isEmpty()
            ? collect()
            : $scheme->batches()->withCount('assistance')->get()->keyBy('id');

        return $scheme->batches
            ->map(static function (Program $batch) use ($requestCounts): array {
                $counted = $requestCounts->get($batch->id);
                $status = $batch->approval_status instanceof ProgramApprovalStatus
                    ? $batch->approval_status
                    : ProgramApprovalStatus::Approved;

                return [
                    'id' => $batch->id,
                    'name' => $batch->name,
                    'batch_name' => $batch->batch_name,
                    'batch_number' => $batch->batch_number,
                    'start_at' => $batch->start_at,
                    'end_at' => $batch->end_at,
                    'is_closed' => (bool) $batch->is_closed,
                    'public_intake' => (bool) $batch->public_intake,
                    'total_requests' => (int) ($counted?->assistance_count ?? 0),
                    'approval_status' => $status->value,
                    'approval_label' => $status->label(),
                    'can_submit_approval' => Gate::allows('submit', $batch)
                        && in_array($status, [
                            ProgramApprovalStatus::Draft,
                            ProgramApprovalStatus::Returned,
                        ], true),
                ];
            })
            ->values()
            ->all();
    }

    /**
     * @param  array<string, mixed>  $validated
     */
    private function updateScheme(Program $program, array $validated): void
    {
        $previousName = $program->name;

        $payload = [
            'name' => $validated['name'],
            'descriptions' => $validated['descriptions'],
            'start_at' => $validated['start_at'],
            'end_at' => $validated['end_at'] ?? null,
        ];

        if (array_key_exists('workflow_id', $validated)) {
            $payload['workflow_id'] = $validated['workflow_id'];
        }

        if (! $program->batches()->exists()) {
            $payload['is_organization'] = $validated['is_organization'] ?? $program->is_organization;
        }

        $program->update($payload);
        $program->item()->sync($validated['item_ids']);

        if (array_key_exists('fields', $validated)) {
            $this->programFieldService->syncForProgram($program, $validated['fields'] ?? []);
        }

        if (array_key_exists('document_requirements', $validated)) {
            $this->programDocumentRequirementService->syncForProgram(
                $program,
                $validated['document_requirements'] ?? [],
            );
        }

        $this->syncEligibility($program, $validated);

        if ($previousName !== $program->name) {
            $program->batches()->each(function (Program $batch) use ($program): void {
                $batchName = $batch->batch_name ?? 'Batch';
                $batch->update([
                    'name' => Program::composeBatchDisplayName($program->name, $batchName),
                ]);
            });
        }

        $this->forgetDashboardFilterOptions($program->department_id);
    }

    /**
     * @param  array<string, mixed>  $validated
     */
    private function updateBatch(Program $program, array $validated): void
    {
        $batchName = $validated['batch_name'] ?? $program->batch_name ?? 'Batch';
        $parent = $program->parent;
        $schemeName = $parent instanceof Program ? $parent->name : $program->name;

        $payload = [
            'batch_name' => $batchName,
            'name' => Program::composeBatchDisplayName($schemeName, $batchName),
            'descriptions' => $validated['descriptions'] ?? $program->descriptions,
            'start_at' => $validated['start_at'],
            'end_at' => $validated['end_at'] ?? null,
            'is_closed' => $validated['is_closed'] ?? false,
            'public_intake' => $validated['public_intake'] ?? false,
        ];

        if (array_key_exists('workflow_id', $validated)) {
            $payload['workflow_id'] = $validated['workflow_id'];
        }

        $program->update($payload);

        $program->fund()->sync($validated['fund_ids'] ?? []);
        $program->item()->sync($validated['item_ids']);

        if (array_key_exists('fields', $validated)) {
            $this->programFieldService->syncForProgram($program, $validated['fields'] ?? []);
        }

        if (array_key_exists('document_requirements', $validated)) {
            $this->programDocumentRequirementService->syncForProgram(
                $program,
                $validated['document_requirements'] ?? [],
            );
        }

        $this->syncSchemeClosedState($program);
        $this->forgetDashboardFilterOptions($program->department_id);
    }

    public function syncSchemeClosedState(Program $program): void
    {
        $scheme = $program->isBatch() ? $program->parent : ($program->isScheme() ? $program : null);

        if (! $scheme instanceof Program) {
            return;
        }

        $hasOpenBatch = $scheme->batches()->where('is_closed', false)->exists();
        $hasBatches = $scheme->batches()->exists();
        $shouldClose = $hasBatches && ! $hasOpenBatch;

        if ((bool) $scheme->is_closed !== $shouldClose) {
            $scheme->update(['is_closed' => $shouldClose]);
        }
    }

    private function forgetDashboardFilterOptions(int $departmentId): void
    {
        Cache::forget("dashboard.filter_options.{$departmentId}");
    }

    private function programDateForInput(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        return Carbon::parse($value)->toDateString();
    }
}
