<?php

namespace App\Services\User;

use App\Actions\User\CreateProgramBatch;
use App\Enums\DocumentRequirementMilestone;
use App\Enums\ItemKind;
use App\Enums\ProgramApprovalAction;
use App\Enums\ProgramApprovalStatus;
use App\Enums\ProgramDistributionMode;
use App\Enums\ProgramFieldType;
use App\Enums\ProgramKind;
use App\Enums\RoleName;
use App\Models\Department;
use App\Models\Fund;
use App\Models\Individual;
use App\Models\Item;
use App\Models\Program;
use App\Models\ProgramDocumentRequirement;
use App\Models\ProgramEligibilityRule;
use App\Models\ProgramField;
use App\Models\ProgramItemCap;
use App\Models\User;
use App\Models\Workflow;
use App\Services\Workflow\EnsureDepartmentWorkflow;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
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
        private EnsureDepartmentWorkflow $ensureDepartmentWorkflow,
    ) {}

    /**
     * @param  list<string>  $types
     * @param  list<string>  $statuses
     * @param  list<string>  $approvals
     */
    public function paginateForDepartment(
        Department $department,
        string $search,
        array $types,
        array $statuses,
        int $perPage = self::PROGRAMS_PER_PAGE,
        array $approvals = [],
    ): LengthAwarePaginator {
        return $this->departmentIndexQuery($department, $search, $types, $statuses)
            ->when(
                $approvals !== [],
                fn (Builder $query) => $query->whereIn('approval_status', $approvals),
            )
            ->orderByDesc('id')
            ->paginate($perPage)
            ->withQueryString()
            ->through(fn (Program $program): array => $this->indexRow($program));
    }

    /**
     * Counts ignore the approval filter so the status pills stay stable.
     *
     * @param  list<string>  $types
     * @param  list<string>  $statuses
     * @return array{all: int, draft: int, awaiting_governor: int, approved: int, returned: int}
     */
    public function approvalCounts(
        Department $department,
        string $search,
        array $types,
        array $statuses,
    ): array {
        $rows = $this->applyDepartmentIndexFilters(
            Program::query(),
            $department,
            $search,
            $types,
            $statuses,
        )
            ->selectRaw('approval_status, COUNT(*) as aggregate')
            ->groupBy('approval_status')
            ->pluck('aggregate', 'approval_status');

        $draft = (int) ($rows[ProgramApprovalStatus::Draft->value] ?? 0);
        $awaiting = (int) ($rows[ProgramApprovalStatus::AwaitingGovernor->value] ?? 0);
        $approved = (int) ($rows[ProgramApprovalStatus::Approved->value] ?? 0);
        $returned = (int) ($rows[ProgramApprovalStatus::Returned->value] ?? 0);

        return [
            'all' => (int) $this->applyDepartmentIndexFilters(
                Program::query(),
                $department,
                $search,
                $types,
                $statuses,
            )->count(),
            'draft' => $draft,
            'awaiting_governor' => $awaiting,
            'approved' => $approved,
            'returned' => $returned,
        ];
    }

    /**
     * @param  Collection<int, Program|array<string, mixed>>  $programs
     * @return array<int, array<string, mixed>>
     */
    /**
     * Programs whose assistances belong to this program, including batches of a scheme.
     *
     * @return list<int>
     */
    public function assistanceProgramIds(Program $program): array
    {
        return $this->metricScope(collect([$program]))['scope_ids'];
    }

    public function cardMetrics(Collection $programs): array
    {
        if ($programs->isEmpty()) {
            return [];
        }

        $models = $this->metricPrograms($programs);

        if ($models->isEmpty()) {
            return [];
        }

        $scope = $this->metricScope($models);
        $roots = $scope['roots'];
        $childToRoot = $scope['child_to_root'];
        $scopeIds = $scope['scope_ids'];

        $metrics = [];

        foreach ($roots as $rootId) {
            $metrics[$rootId] = $this->emptyCardMetric();
        }

        $this->applyBudgetMetrics($metrics, $childToRoot, $scopeIds);
        $this->applyItemKindMetrics($metrics, $childToRoot, $scopeIds);
        $this->applyRequestMetrics($metrics, $childToRoot, $scopeIds);
        $this->applyPeopleMetrics($metrics, $childToRoot, $scopeIds);
        $this->applyApprovalEventMetrics($metrics, $childToRoot, $scopeIds);

        return $metrics;
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
            'distribution_mode' => $this->distributionMode($validated),
            'kind' => $kind,
            'approval_status' => ProgramApprovalStatus::Draft,
            'workflow_id' => $this->workflowIdForAssignment(
                $department,
                $validated['workflow_id'] ?? null,
            ),
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

        if (array_key_exists('distribution_mode', $validated)) {
            $payload['distribution_mode'] = $this->distributionMode($validated);
        }

        if (array_key_exists('workflow_id', $validated)) {
            $payload['workflow_id'] = $this->workflowIdForAssignment(
                $this->departmentOf($program),
                $validated['workflow_id'],
            );
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
     * Program configuration the executive reviews, without beneficiaries.
     *
     * @return array{
     *     start_at: string|null,
     *     end_at: string|null,
     *     is_closed: bool,
     *     is_organization: bool,
     *     public_intake: bool,
     *     kind_label: string|null,
     *     batch_name: string|null,
     *     parent: array{id: int, name: string}|null,
     *     funds: list<array{id: int, name: string, year: string|null, amount: float|null}>,
     *     items: list<array{id: int, name: string, unit: string|null}>,
     *     fields: list<array{id: int, label: string, type_label: string, is_required: bool, options: list<string>|null}>,
     *     document_requirements: list<array{id: int, name: string, is_required: bool, required_before_label: string}>,
     *     eligibility: array{
     *         cooldown_days: int|null,
     *         requirements: list<string>,
     *         item_caps: list<array{item_id: int, item_name: string, max_released_per_year: int}>,
     *         source_name: string|null
     *     }
     * }
     */
    public function executiveReviewPayload(Program $program): array
    {
        $program->loadMissing([
            'parent:id,name',
            'item.unitMeasurement:id,name',
            'fields',
            'documentRequirements.documentType:id,name',
        ]);

        $parent = $program->parent;
        $kind = $program->kind;

        return [
            'start_at' => $this->programDateLabel($program->getRawOriginal('start_at')),
            'end_at' => $this->programDateLabel($program->getRawOriginal('end_at')),
            'is_closed' => (bool) $program->is_closed,
            'is_organization' => (bool) $program->is_organization,
            'public_intake' => (bool) $program->public_intake,
            'kind_label' => $kind instanceof ProgramKind ? $kind->label() : null,
            'batch_name' => $program->batch_name,
            'parent' => $parent instanceof Program
                ? [
                    'id' => $parent->id,
                    'name' => $parent->name,
                ]
                : null,
            'funds' => $this->programFundsForDisplay($program),
            'items' => $program->item
                ->sortBy('name', SORT_NATURAL | SORT_FLAG_CASE)
                ->values()
                ->map(static fn (Item $item): array => [
                    'id' => $item->id,
                    'name' => $item->name,
                    'unit' => $item->unitMeasurement?->name,
                ])
                ->all(),
            'fields' => $program->fields
                ->map(static function (ProgramField $field): array {
                    $type = $field->type;

                    return [
                        'id' => $field->id,
                        'label' => $field->label,
                        'type_label' => $type instanceof ProgramFieldType ? $type->name : (string) $type,
                        'is_required' => (bool) $field->is_required,
                        'options' => $field->options,
                    ];
                })
                ->values()
                ->all(),
            'document_requirements' => $program->documentRequirements
                ->map(static function (ProgramDocumentRequirement $requirement): array {
                    return [
                        'id' => $requirement->id,
                        'name' => $requirement->documentType?->name ?? 'Document',
                        'is_required' => (bool) $requirement->is_required,
                        'required_before_label' => match ($requirement->required_before) {
                            DocumentRequirementMilestone::Verified => 'Before verification',
                            DocumentRequirementMilestone::Delivered => 'Before delivery',
                            default => 'Not set',
                        },
                    ];
                })
                ->values()
                ->all(),
            'eligibility' => $this->executiveEligibility($program),
        ];
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
                'distribution_mode',
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
            'document_requirements' => $this->documentRequirementsPayload($program),
            'eligibility' => $this->eligibilityPayload($program),
            'workflow_id' => $program->workflow_id,
        ];
    }

    /**
     * @return list<array{
     *     id: int,
     *     document_type_id: int,
     *     is_required: bool,
     *     required_before: string,
     *     sort_order: int
     * }>
     */
    public function documentRequirementsPayload(Program $program): array
    {
        return $this->programDocumentRequirementService->requirementsPayload($program);
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
     *     requirements: list<string>,
     *     item_caps: list<array{item_id: int, item_name: string, max_released_per_year: int}>,
     *     source_name: string|null
     * }
     */
    private function executiveEligibility(Program $program): array
    {
        $source = $program->eligibilityProgram();
        $source->loadMissing(['eligibilityRule', 'itemCaps.item:id,name']);
        $rule = $source->eligibilityRule;
        $requirements = [];

        if ($rule instanceof ProgramEligibilityRule) {
            if ($rule->require_pwd) {
                $requirements[] = 'PWD only';
            }

            if ($rule->require_4ps) {
                $requirements[] = '4Ps only';
            }

            if ($rule->require_solo_parent) {
                $requirements[] = 'Solo parent only';
            }

            if ($rule->require_indigenous) {
                $requirements[] = 'Indigenous peoples only';
            }
        }

        return [
            'cooldown_days' => $rule instanceof ProgramEligibilityRule ? $rule->cooldown_days : null,
            'requirements' => $requirements,
            'item_caps' => $source->itemCaps
                ->map(static fn (ProgramItemCap $cap): array => [
                    'item_id' => $cap->item_id,
                    'item_name' => $cap->item?->name ?? 'Item',
                    'max_released_per_year' => $cap->max_released_per_year,
                ])
                ->values()
                ->all(),
            'source_name' => $source->is($program) ? null : $source->name,
        ];
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
            ->map(function (Program $batch) use ($requestCounts): array {
                $counted = $requestCounts->get($batch->id);
                $status = $batch->approval_status instanceof ProgramApprovalStatus
                    ? $batch->approval_status
                    : ProgramApprovalStatus::Approved;

                return [
                    'id' => $batch->id,
                    'name' => $batch->name,
                    'descriptions' => $batch->descriptions,
                    'batch_name' => $batch->batch_name,
                    'batch_number' => $batch->batch_number,
                    'start_at' => $batch->start_at,
                    'end_at' => $batch->end_at,
                    'is_closed' => (bool) $batch->is_closed,
                    'is_organization' => (bool) $batch->is_organization,
                    'public_intake' => (bool) $batch->public_intake,
                    'kind' => $batch->kind instanceof ProgramKind ? $batch->kind->value : $batch->kind,
                    'total_requests' => (int) ($counted?->assistance_count ?? 0),
                    'approval_status' => $status->value,
                    'approval_label' => $status->label(),
                    'can_submit_approval' => $this->canEndorse($batch)
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
            $payload['workflow_id'] = $this->workflowIdForAssignment(
                $this->departmentOf($program),
                $validated['workflow_id'],
            );
        }

        if (array_key_exists('distribution_mode', $validated)) {
            $payload['distribution_mode'] = $this->distributionMode($validated);
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

        $program->batches()->update([
            'distribution_mode' => $program->resolvedDistributionMode()->value,
        ]);

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
            $payload['workflow_id'] = $this->workflowIdForAssignment(
                $this->departmentOf($program),
                $validated['workflow_id'],
            );
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

    /**
     * @param  list<string>  $types
     * @param  list<string>  $statuses
     * @return Builder<Program>
     */
    private function departmentIndexQuery(
        Department $department,
        string $search,
        array $types,
        array $statuses,
    ): Builder {
        return $this->applyDepartmentIndexFilters(
            Program::query()
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
                    'approval_status',
                    'batch_name',
                ])
                ->with(['department:id,name,slug'])
                ->withCount([
                    'batches',
                    'batches as open_batches_count' => fn (Builder $query) => $query->where('is_closed', false),
                ]),
            $department,
            $search,
            $types,
            $statuses,
        );
    }

    /**
     * @param  Builder<Program>  $query
     * @param  list<string>  $types
     * @param  list<string>  $statuses
     * @return Builder<Program>
     */
    private function applyDepartmentIndexFilters(
        Builder $query,
        Department $department,
        string $search,
        array $types,
        array $statuses,
    ): Builder {
        return $query
            ->roots()
            ->where('department_id', $department->id)
            ->when($search !== '', function (Builder $builder) use ($search): void {
                $builder->where(function (Builder $searchQuery) use ($search): void {
                    $searchQuery
                        ->where('name', 'like', '%'.$search.'%')
                        ->orWhereHas('batches', function (Builder $batchQuery) use ($search): void {
                            $batchQuery
                                ->where('name', 'like', '%'.$search.'%')
                                ->orWhere('batch_name', 'like', '%'.$search.'%');
                        });
                });
            })
            ->when(
                count($types) === 1 && in_array('individual', $types, true),
                fn (Builder $builder) => $builder->where('is_organization', false),
            )
            ->when(
                count($types) === 1 && in_array('organization', $types, true),
                fn (Builder $builder) => $builder->where('is_organization', true),
            )
            ->when(
                count($statuses) === 1 && in_array('open', $statuses, true),
                fn (Builder $builder) => $builder->where('is_closed', false),
            )
            ->when(
                count($statuses) === 1 && in_array('closed', $statuses, true),
                fn (Builder $builder) => $builder->where('is_closed', true),
            );
    }

    /**
     * @return array<string, mixed>
     */
    private function indexRow(Program $program): array
    {
        $status = $program->approval_status instanceof ProgramApprovalStatus
            ? $program->approval_status
            : ProgramApprovalStatus::Draft;

        return [
            'id' => $program->id,
            'name' => $program->name,
            'descriptions' => $program->descriptions,
            'start_at' => $program->start_at,
            'end_at' => $program->end_at,
            'is_closed' => (bool) $program->is_closed,
            'is_organization' => (bool) $program->is_organization,
            'public_intake' => (bool) $program->public_intake,
            'distribution_mode' => $program->resolvedDistributionMode()->value,
            'kind' => $program->kind instanceof ProgramKind ? $program->kind->value : $program->kind,
            'batch_name' => $program->batch_name,
            'batches_count' => (int) $program->batches_count,
            'open_batches_count' => (int) $program->open_batches_count,
            'approval_status' => $status->value,
            'approval_label' => $status->label(),
            'can_submit_approval' => $this->canEndorse($program),
            'department' => $program->department === null ? null : [
                'id' => $program->department->id,
                'name' => $program->department->name,
                'slug' => $program->department->slug,
            ],
        ];
    }

    /**
     * @param  Collection<int, Program|array<string, mixed>>  $programs
     * @return Collection<int, Program>
     */
    private function metricPrograms(Collection $programs): Collection
    {
        $first = $programs->first();

        if ($first instanceof Program) {
            return $programs->filter(fn (mixed $program): bool => $program instanceof Program)->values();
        }

        $ids = $programs
            ->map(function (mixed $program): int {
                if (is_array($program)) {
                    return (int) ($program['id'] ?? 0);
                }

                return (int) ($program->id ?? 0);
            })
            ->filter(fn (int $id): bool => $id > 0)
            ->values()
            ->all();

        return Program::query()
            ->whereIn('id', $ids)
            ->get(['id', 'kind', 'parent_id']);
    }

    /**
     * @param  Collection<int, Program>  $programs
     * @return array{roots: list<int>, child_to_root: array<int, int>, scope_ids: list<int>}
     */
    private function metricScope(Collection $programs): array
    {
        $schemeIds = $programs
            ->filter(fn (Program $program): bool => $program->isScheme())
            ->pluck('id')
            ->all();

        $batches = $schemeIds === []
            ? collect()
            : Program::query()
                ->whereIn('parent_id', $schemeIds)
                ->get(['id', 'parent_id']);

        $childToRoot = [];
        $scopeIds = [];

        foreach ($programs as $program) {
            $rootId = $program->id;

            if ($program->isScheme()) {
                $children = $batches->where('parent_id', $rootId)->pluck('id');

                if ($children->isEmpty()) {
                    $childToRoot[$rootId] = $rootId;
                    $scopeIds[] = $rootId;

                    continue;
                }

                foreach ($children as $childId) {
                    $childToRoot[(int) $childId] = $rootId;
                    $scopeIds[] = (int) $childId;
                }

                continue;
            }

            $childToRoot[$rootId] = $rootId;
            $scopeIds[] = $rootId;
        }

        return [
            'roots' => $programs->pluck('id')->map(fn (mixed $id): int => (int) $id)->all(),
            'child_to_root' => $childToRoot,
            'scope_ids' => array_values(array_unique($scopeIds)),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function emptyCardMetric(): array
    {
        return [
            'budget' => 0.0,
            'cash_released' => 0,
            'goods_released' => 0,
            'item_kinds' => [],
            'total_requests' => 0,
            'delivered_requests' => 0,
            'claimants' => 0,
            'mean_age' => null,
            'female' => 0,
            'male' => 0,
            'other' => 0,
            'pwd' => 0,
            'individuals' => 0,
            'submitted_at' => null,
            'approved_at' => null,
        ];
    }

    /**
     * @param  array<int, array<string, mixed>>  $metrics
     * @param  array<int, int>  $childToRoot
     * @param  list<int>  $scopeIds
     */
    private function applyBudgetMetrics(array &$metrics, array $childToRoot, array $scopeIds): void
    {
        if ($scopeIds === []) {
            return;
        }

        $rows = DB::table('fund_program')
            ->join('funds', 'funds.id', '=', 'fund_program.fund_id')
            ->whereIn('fund_program.program_id', $scopeIds)
            ->whereNull('fund_program.deleted_at')
            ->whereNull('funds.deleted_at')
            ->get(['fund_program.program_id', 'funds.id as fund_id', 'funds.amount']);

        $seen = [];

        foreach ($rows as $row) {
            $rootId = $childToRoot[(int) $row->program_id] ?? null;

            if ($rootId === null || ! isset($metrics[$rootId])) {
                continue;
            }

            $key = $rootId.'-'.$row->fund_id;

            if (isset($seen[$key])) {
                continue;
            }

            $seen[$key] = true;
            $metrics[$rootId]['budget'] += (float) $row->amount;
        }
    }

    /**
     * @param  array<int, array<string, mixed>>  $metrics
     * @param  array<int, int>  $childToRoot
     * @param  list<int>  $scopeIds
     */
    private function applyItemKindMetrics(array &$metrics, array $childToRoot, array $scopeIds): void
    {
        if ($scopeIds === []) {
            return;
        }

        $rows = DB::table('item_program')
            ->join('items', 'items.id', '=', 'item_program.item_id')
            ->whereIn('item_program.program_id', $scopeIds)
            ->whereNull('item_program.deleted_at')
            ->whereNull('items.deleted_at')
            ->distinct()
            ->get(['item_program.program_id', 'items.kind']);

        foreach ($rows as $row) {
            $rootId = $childToRoot[(int) $row->program_id] ?? null;

            if ($rootId === null || ! isset($metrics[$rootId]) || $row->kind === null) {
                continue;
            }

            if (! in_array($row->kind, $metrics[$rootId]['item_kinds'], true)) {
                $metrics[$rootId]['item_kinds'][] = (string) $row->kind;
            }
        }
    }

    /**
     * @param  array<int, array<string, mixed>>  $metrics
     * @param  array<int, int>  $childToRoot
     * @param  list<int>  $scopeIds
     */
    private function applyRequestMetrics(array &$metrics, array $childToRoot, array $scopeIds): void
    {
        if ($scopeIds === []) {
            return;
        }

        $rows = DB::table('assistances')
            ->whereIn('program_id', $scopeIds)
            ->whereNull('deleted_at')
            ->select('program_id')
            ->selectRaw('COUNT(*) as total_requests')
            ->selectRaw('SUM(CASE WHEN was_delivered = 1 THEN 1 ELSE 0 END) as delivered_requests')
            ->selectRaw('COUNT(DISTINCT CASE WHEN was_delivered = 1 THEN beneficiary_id END) as claimants')
            ->groupBy('program_id')
            ->get();

        foreach ($rows as $row) {
            $rootId = $childToRoot[(int) $row->program_id] ?? null;

            if ($rootId === null || ! isset($metrics[$rootId])) {
                continue;
            }

            $metrics[$rootId]['total_requests'] += (int) $row->total_requests;
            $metrics[$rootId]['delivered_requests'] += (int) $row->delivered_requests;
            $metrics[$rootId]['claimants'] += (int) $row->claimants;
        }

        $released = DB::table('assistance_item as ai')
            ->join('assistances', 'assistances.id', '=', 'ai.assistance_id')
            ->join('items', 'items.id', '=', 'ai.item_id')
            ->whereIn('assistances.program_id', $scopeIds)
            ->where('ai.is_received', true)
            ->whereNull('ai.deleted_at')
            ->whereNull('assistances.deleted_at')
            ->select('assistances.program_id', 'items.kind')
            ->selectRaw('SUM(ai.quantity) as total')
            ->groupBy('assistances.program_id', 'items.kind')
            ->get();

        foreach ($released as $row) {
            $rootId = $childToRoot[(int) $row->program_id] ?? null;

            if ($rootId === null || ! isset($metrics[$rootId])) {
                continue;
            }

            if ($row->kind === ItemKind::Cash->value) {
                $metrics[$rootId]['cash_released'] += (int) $row->total;
            }

            if ($row->kind === ItemKind::Goods->value) {
                $metrics[$rootId]['goods_released'] += (int) $row->total;
            }
        }
    }

    /**
     * @param  array<int, array<string, mixed>>  $metrics
     * @param  array<int, int>  $childToRoot
     * @param  list<int>  $scopeIds
     */
    private function applyPeopleMetrics(array &$metrics, array $childToRoot, array $scopeIds): void
    {
        if ($scopeIds === []) {
            return;
        }

        $rows = DB::table('assistances')
            ->join('beneficiaries', 'beneficiaries.id', '=', 'assistances.beneficiary_id')
            ->join('individuals', function ($join): void {
                $join->on('beneficiaries.beneficiable_id', '=', 'individuals.id')
                    ->where('beneficiaries.beneficiable_type', '=', Individual::class);
            })
            ->whereIn('assistances.program_id', $scopeIds)
            ->whereNull('assistances.deleted_at')
            ->whereNull('beneficiaries.deleted_at')
            ->whereNull('individuals.deleted_at')
            ->groupBy('assistances.program_id', 'individuals.id')
            ->get([
                'assistances.program_id',
                'individuals.id',
                DB::raw('MAX(individuals.sex) as sex'),
                DB::raw('MAX(individuals.pwd) as pwd'),
                DB::raw('MAX(individuals.birthday) as birthday'),
            ]);

        /** @var array<int, array{ages: list<int>, female: int, male: int, other: int, pwd: int}> $buckets */
        $buckets = [];

        foreach ($rows as $row) {
            $rootId = $childToRoot[(int) $row->program_id] ?? null;

            if ($rootId === null) {
                continue;
            }

            $buckets[$rootId] ??= [
                'ages' => [],
                'female' => 0,
                'male' => 0,
                'other' => 0,
                'pwd' => 0,
            ];

            $sex = strtolower(trim((string) $row->sex));

            if ($sex === 'female') {
                $buckets[$rootId]['female']++;
            } elseif ($sex === 'male') {
                $buckets[$rootId]['male']++;
            } else {
                $buckets[$rootId]['other']++;
            }

            if ((int) $row->pwd === 1) {
                $buckets[$rootId]['pwd']++;
            }

            if ($row->birthday !== null) {
                $buckets[$rootId]['ages'][] = (int) Carbon::parse($row->birthday)->age;
            }
        }

        foreach ($buckets as $rootId => $bucket) {
            if (! isset($metrics[$rootId])) {
                continue;
            }

            $metrics[$rootId]['female'] = $bucket['female'];
            $metrics[$rootId]['male'] = $bucket['male'];
            $metrics[$rootId]['other'] = $bucket['other'];
            $metrics[$rootId]['pwd'] = $bucket['pwd'];
            $metrics[$rootId]['individuals'] = $bucket['female'] + $bucket['male'] + $bucket['other'];
            $metrics[$rootId]['mean_age'] = $bucket['ages'] === []
                ? null
                : round(array_sum($bucket['ages']) / count($bucket['ages']), 1);
        }
    }

    private function canEndorse(Program $program): bool
    {
        $user = auth()->user();

        return $user instanceof User
            && $user->hasRole(RoleName::DepartmentHead->value)
            && Gate::allows('submit', $program);
    }

    /**
     * @param  array<int, array<string, mixed>>  $metrics
     * @param  array<int, int>  $childToRoot
     * @param  list<int>  $scopeIds
     */
    private function applyApprovalEventMetrics(array &$metrics, array $childToRoot, array $scopeIds): void
    {
        if ($scopeIds === []) {
            return;
        }

        $rows = DB::table('program_approval_events')
            ->whereIn('program_id', $scopeIds)
            ->whereIn('action', [
                ProgramApprovalAction::Submitted->value,
                ProgramApprovalAction::Approved->value,
            ])
            ->select('program_id', 'action')
            ->selectRaw('MAX(created_at) as happened_at')
            ->groupBy('program_id', 'action')
            ->get();

        foreach ($rows as $row) {
            $rootId = $childToRoot[(int) $row->program_id] ?? null;

            if ($rootId === null || ! isset($metrics[$rootId]) || $row->happened_at === null) {
                continue;
            }

            $key = $row->action === ProgramApprovalAction::Approved->value
                ? 'approved_at'
                : 'submitted_at';
            $current = $metrics[$rootId][$key];

            if ($current === null || $row->happened_at > $current) {
                $metrics[$rootId][$key] = (string) $row->happened_at;
            }
        }
    }

    private function programDateLabel(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        return Carbon::parse($value)->format('M d, Y');
    }

    private function departmentOf(Program $program): Department
    {
        $program->loadMissing('department');

        $department = $program->department;

        if ($department instanceof Department) {
            return $department;
        }

        return Department::query()->findOrFail($program->department_id);
    }

    /**
     * Pin the program to an explicit workflow, or to the department default.
     */
    private function workflowIdForAssignment(Department $department, mixed $workflowId): int
    {
        $workflow = null;

        if (is_numeric($workflowId)) {
            $workflow = Workflow::query()
                ->where('department_id', $department->id)
                ->whereKey((int) $workflowId)
                ->first();
        }

        if (! $workflow instanceof Workflow) {
            $workflow = $this->ensureDepartmentWorkflow->defaultFor($department);
        }

        return $workflow->activeVersion()->id;
    }

    /**
     * @param  array<string, mixed>  $validated
     */
    private function distributionMode(array $validated): ProgramDistributionMode
    {
        return ProgramDistributionMode::tryFrom((string) ($validated['distribution_mode'] ?? ''))
            ?? ProgramDistributionMode::Batch;
    }
}
