<?php

namespace App\Services\User;

use App\Actions\User\ApplyDashboardFilters;
use App\Actions\User\JoinAssistanceStatusRelations;
use App\Models\Assistance;
use App\Models\AssistanceItem;
use App\Models\Department;
use App\Models\Individual;
use App\Models\Item;
use App\Models\Program;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Support\Facades\DB;

class DashboardService
{
    private const TERMINAL_STATUSES = ['Delivered', 'Denied', 'Closed'];

    public function __construct(
        private ApplyDashboardFilters $applyDashboardFilters,
        private JoinAssistanceStatusRelations $joinAssistanceStatusRelations,
    ) {}

    /**
     * @param  array{
     *     program?: list<int>,
     *     beneficiary_type?: list<string>,
     *     sex?: list<string>,
     *     pwd?: list<string>,
     *     four_ps?: list<string>,
     *     solo_parent?: list<string>,
     *     indigenous?: list<string>
     * }  $filters
     * @return array<string, mixed>
     */
    public function payload(Department $department, array $filters): array
    {
        return [
            'summary' => $this->summary($department, $filters),
            'requestStatusChart' => $this->requestStatusChart($department, $filters),
            'deliveredItemsChart' => $this->deliveredItemsChart($department, $filters),
            'programsTable' => $this->programsTable($department, $filters),
            'filterOptions' => $this->filterOptions($department),
            'filters' => $this->serializeFilters($filters),
        ];
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return array{
     *     program: list<string>,
     *     beneficiary_type: list<string>,
     *     sex: list<string>,
     *     pwd: list<string>,
     *     four_ps: list<string>,
     *     solo_parent: list<string>,
     *     indigenous: list<string>
     * }
     */
    public function serializeFilters(array $filters): array
    {
        return [
            'program' => array_map('strval', $filters['program'] ?? []),
            'beneficiary_type' => $filters['beneficiary_type'] ?? [],
            'sex' => $filters['sex'] ?? [],
            'pwd' => $filters['pwd'] ?? [],
            'four_ps' => $filters['four_ps'] ?? [],
            'solo_parent' => $filters['solo_parent'] ?? [],
            'indigenous' => $filters['indigenous'] ?? [],
        ];
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return array{
     *     total_requests: int,
     *     delivered_requests: int,
     *     total_delivered_items: int,
     *     active_programs: int
     * }
     */
    public function summary(Department $department, array $filters): array
    {
        $deliveredSql = $this->isDeliveredSql();

        $stats = (clone $this->filteredAssistanceQuery($department, $filters))
            ->selectRaw('COUNT(DISTINCT assistances.id) as total_requests')
            ->selectRaw("COUNT(DISTINCT CASE WHEN {$deliveredSql} THEN assistances.id END) as delivered_requests")
            ->toBase()
            ->first();

        return [
            'total_requests' => (int) ($stats->total_requests ?? 0),
            'delivered_requests' => (int) ($stats->delivered_requests ?? 0),
            'total_delivered_items' => $this->sumDeliveredItems($department, $filters),
            'active_programs' => Program::query()
                ->where('department_id', $department->id)
                ->where('is_closed', false)
                ->count(),
        ];
    }

    /**
     * @return array{
     *     total_requests: int,
     *     delivered_requests: int,
     *     in_progress_requests: int,
     *     total_delivered_items: int
     * }
     */
    public function summaryForProgram(Program $program): array
    {
        $program->loadMissing('department:id,name,slug');

        $department = $program->department;

        if ($department === null) {
            $department = Department::query()->findOrFail($program->department_id);
        }

        $filters = ['program' => [$program->id]];
        $statusExpression = $this->resolvedStatusExpression();
        $deliveredSql = $this->isDeliveredSql();
        $terminalList = implode("','", self::TERMINAL_STATUSES);

        $stats = (clone $this->filteredAssistanceQuery($department, $filters))
            ->selectRaw('COUNT(DISTINCT assistances.id) as total_requests')
            ->selectRaw("COUNT(DISTINCT CASE WHEN {$deliveredSql} THEN assistances.id END) as delivered_requests")
            ->selectRaw("COUNT(DISTINCT CASE WHEN {$statusExpression} NOT IN ('{$terminalList}') THEN assistances.id END) as in_progress_requests")
            ->toBase()
            ->first();

        return [
            'total_requests' => (int) ($stats->total_requests ?? 0),
            'delivered_requests' => (int) ($stats->delivered_requests ?? 0),
            'in_progress_requests' => (int) ($stats->in_progress_requests ?? 0),
            'total_delivered_items' => $this->sumDeliveredItems($department, $filters),
        ];
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return list<array{status: string, count: int}>
     */
    public function requestStatusChart(Department $department, array $filters): array
    {
        $statusExpression = $this->resolvedStatusExpression();

        return (clone $this->filteredAssistanceQuery($department, $filters))
            ->selectRaw("{$statusExpression} as resolved_status")
            ->selectRaw('COUNT(DISTINCT assistances.id) as count')
            ->groupByRaw($statusExpression)
            ->orderByDesc('count')
            ->toBase()
            ->get()
            ->map(static fn ($row): array => [
                'status' => (string) $row->resolved_status,
                'count' => (int) $row->count,
            ])
            ->values()
            ->all();
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return list<array{item: string, unit: string, quantity: int}>
     */
    public function deliveredItemsChart(Department $department, array $filters): array
    {
        $itemTable = (new Item)->getTable();

        return DB::table((new AssistanceItem)->getTable().' as ai')
            ->joinSub(
                $this->filteredAssistanceIdsSubquery($department, $filters),
                'filtered_assistances',
                'filtered_assistances.id',
                '=',
                'ai.assistance_id',
            )
            ->join("{$itemTable} as items", 'items.id', '=', 'ai.item_id')
            ->leftJoin('item_unit_measurements as ium', 'ium.id', '=', 'items.item_unit_measurement_id')
            ->where('ai.is_received', true)
            ->whereNull('ai.deleted_at')
            ->select([
                'items.name as item',
                'ium.name as unit',
            ])
            ->selectRaw('SUM(ai.quantity) as quantity')
            ->groupBy('items.name', 'ium.name')
            ->orderByDesc('quantity')
            ->limit(10)
            ->get()
            ->map(static fn ($row): array => [
                'item' => (string) $row->item,
                'unit' => (string) ($row->unit ?? '—'),
                'quantity' => (int) $row->quantity,
            ])
            ->values()
            ->all();
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return list<array{
     *     id: int,
     *     name: string,
     *     type: string,
     *     status: string,
     *     total_requests: int,
     *     delivered: int,
     *     in_progress: int
     * }>
     */
    public function programsTable(Department $department, array $filters): array
    {
        $statusExpression = $this->resolvedStatusExpression();
        $deliveredSql = $this->isDeliveredSql();
        $terminalList = implode("','", self::TERMINAL_STATUSES);

        $demographicFilters = $filters;
        unset($demographicFilters['program']);

        $statsByProgramId = (clone $this->filteredAssistanceQuery($department, $demographicFilters))
            ->select('programs.id')
            ->selectRaw('COUNT(DISTINCT assistances.id) as total_requests')
            ->selectRaw("COUNT(DISTINCT CASE WHEN {$deliveredSql} THEN assistances.id END) as delivered")
            ->selectRaw("COUNT(DISTINCT CASE WHEN {$statusExpression} NOT IN ('{$terminalList}') THEN assistances.id END) as in_progress")
            ->groupBy('programs.id')
            ->toBase()
            ->get()
            ->keyBy('id');

        $programQuery = Program::query()
            ->where('department_id', $department->id)
            ->orderBy('name');

        $selectedPrograms = $filters['program'] ?? [];

        if ($selectedPrograms !== []) {
            $programQuery->whereIn('id', $selectedPrograms);
        }

        return $programQuery
            ->get(['id', 'name', 'is_closed', 'is_organization'])
            ->map(function (Program $program) use ($statsByProgramId): array {
                $stats = $statsByProgramId->get($program->id);

                return [
                    'id' => $program->id,
                    'name' => $program->name,
                    'type' => $program->is_organization ? 'organization' : 'individual',
                    'status' => $program->is_closed ? 'closed' : 'open',
                    'total_requests' => (int) ($stats->total_requests ?? 0),
                    'delivered' => (int) ($stats->delivered ?? 0),
                    'in_progress' => (int) ($stats->in_progress ?? 0),
                ];
            })
            ->values()
            ->all();
    }

    /**
     * @return array{
     *     programs: list<array{label: string, value: string}>,
     *     beneficiary_type: list<array{label: string, value: string}>,
     *     sex: list<array{label: string, value: string}>,
     *     pwd: list<array{label: string, value: string}>,
     *     four_ps: list<array{label: string, value: string}>,
     *     solo_parent: list<array{label: string, value: string}>,
     *     indigenous: list<array{label: string, value: string}>
     * }
     */
    public function filterOptions(Department $department): array
    {
        $programs = Program::query()
            ->where('department_id', $department->id)
            ->orderBy('name')
            ->get(['id', 'name'])
            ->map(static fn (Program $program): array => [
                'label' => $program->name,
                'value' => (string) $program->id,
            ])
            ->values()
            ->all();

        $yesNo = [
            ['label' => 'Yes', 'value' => 'true'],
            ['label' => 'No', 'value' => 'false'],
        ];

        return [
            'programs' => $programs,
            'beneficiary_type' => [
                ['label' => 'Individual', 'value' => 'individual'],
                ['label' => 'Organization', 'value' => 'organization'],
            ],
            'sex' => [
                ['label' => 'Male', 'value' => 'Male'],
                ['label' => 'Female', 'value' => 'Female'],
            ],
            'pwd' => $yesNo,
            'four_ps' => $yesNo,
            'solo_parent' => $yesNo,
            'indigenous' => $yesNo,
        ];
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    private function sumDeliveredItems(Department $department, array $filters): int
    {
        return (int) DB::table((new AssistanceItem)->getTable().' as ai')
            ->joinSub(
                $this->filteredAssistanceIdsSubquery($department, $filters),
                'filtered_assistances',
                'filtered_assistances.id',
                '=',
                'ai.assistance_id',
            )
            ->where('ai.is_received', true)
            ->whereNull('ai.deleted_at')
            ->sum('ai.quantity');
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    private function filteredAssistanceIdsSubquery(Department $department, array $filters): QueryBuilder
    {
        return $this->filteredAssistanceQuery($department, $filters)
            ->select('assistances.id')
            ->distinct()
            ->toBase();
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return Builder<Assistance>
     */
    private function filteredAssistanceQuery(Department $department, array $filters): Builder
    {
        $query = Assistance::query()
            ->join('programs', 'programs.id', '=', 'assistances.program_id')
            ->where('programs.department_id', $department->id)
            ->leftJoin('beneficiaries', 'beneficiaries.id', '=', 'assistances.beneficiary_id')
            ->leftJoin('individuals', function ($join): void {
                $join->on('beneficiaries.beneficiable_id', '=', 'individuals.id')
                    ->where('beneficiaries.beneficiable_type', '=', Individual::class)
                    ->whereNull('individuals.deleted_at');
            });

        ($this->applyDashboardFilters)($query, $filters);
        ($this->joinAssistanceStatusRelations)($query);

        return $query;
    }

    private function isDeliveredSql(): string
    {
        $assistanceItemTable = (new AssistanceItem)->getTable();

        return "(rs.name = 'Delivered' OR (rs.name IS NULL AND assistances.date_delivered IS NOT NULL AND EXISTS (SELECT 1 FROM {$assistanceItemTable} ai WHERE ai.assistance_id = assistances.id AND ai.is_received = 1 AND ai.deleted_at IS NULL)))";
    }

    private function resolvedStatusExpression(): string
    {
        return "COALESCE(rs.name, CASE
            WHEN assistances.date_denied IS NOT NULL THEN 'Denied'
            WHEN assistances.date_delivered IS NOT NULL THEN 'Delivered'
            WHEN assistances.date_verified IS NOT NULL THEN 'Verified'
            WHEN assistances.date_requested IS NOT NULL THEN 'Pending'
            ELSE 'Unrequested'
        END)";
    }
}
