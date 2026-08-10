<?php

namespace App\Services\User;

use App\Actions\User\ApplyDashboardFilters;
use App\Actions\User\JoinAssistanceStatusRelations;
use App\Models\Assistance;
use App\Models\AssistanceItem;
use App\Models\Department;
use App\Models\Individual;
use App\Models\Item;
use App\Models\Organization;
use App\Models\Program;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class DashboardService
{
    private const TERMINAL_STATUSES = ['Delivered', 'Denied', 'Closed'];

    private const PROGRAMS_TABLE_LIMIT = 10;

    public function __construct(
        private ApplyDashboardFilters $applyDashboardFilters,
        private JoinAssistanceStatusRelations $joinAssistanceStatusRelations,
    ) {}

    /**
     * @param  array{
     *     year?: list<int>,
     *     quarter?: list<string>,
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
            'beneficiaryTypeChart' => $this->beneficiaryTypeChart($department, $filters),
            'demographics' => $this->demographics($department, $filters),
            'requestsTrend' => $this->requestsTrend($department, $filters),
            'insights' => $this->insights($department, $filters),
            'topBarangays' => $this->topBarangays($department, $filters),
            'modeOfRequestChart' => $this->modeOfRequestChart($department, $filters),
            'filterOptions' => $this->filterOptions($department),
            'filters' => $this->serializeFilters($filters),
        ];
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return array{
     *     year: list<string>,
     *     quarter: list<string>,
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
            'year' => array_map('strval', $filters['year'] ?? []),
            'quarter' => $filters['quarter'] ?? [],
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
     *     active_programs: int,
     *     in_progress_requests: int,
     *     denied_requests: int,
     *     unique_beneficiaries: int,
     *     closed_programs: int,
     *     avg_days_to_deliver: float|null,
     *     avg_days_to_verify: float|null,
     *     repeat_beneficiaries: int,
     *     one_time_beneficiaries: int,
     *     avg_requests_per_beneficiary: float
     * }
     */
    public function summary(Department $department, array $filters): array
    {
        $deliveredSql = $this->isDeliveredSql();
        $statusExpression = $this->resolvedStatusExpression();
        $terminalList = implode("','", self::TERMINAL_STATUSES);

        $firstVerifiedAt = $this->dateDiffExpression('first_verified.verified_at', 'assistances.date_requested');

        $stats = (clone $this->filteredAssistanceQuery($department, $filters))
            ->leftJoinSub($this->firstVerifiedAtSubquery(), 'first_verified', 'first_verified.assistance_id', '=', 'assistances.id')
            ->selectRaw('COUNT(DISTINCT assistances.id) as total_requests')
            ->selectRaw("COUNT(DISTINCT CASE WHEN {$deliveredSql} THEN assistances.id END) as delivered_requests")
            ->selectRaw("COUNT(DISTINCT CASE WHEN {$statusExpression} NOT IN ('{$terminalList}') THEN assistances.id END) as in_progress_requests")
            ->selectRaw("COUNT(DISTINCT CASE WHEN {$statusExpression} = 'Denied' THEN assistances.id END) as denied_requests")
            ->selectRaw('COUNT(DISTINCT assistances.beneficiary_id) as unique_beneficiaries')
            ->selectRaw("AVG(CASE WHEN assistances.date_delivered IS NOT NULL AND assistances.date_requested IS NOT NULL THEN {$this->dateDiffExpression('assistances.date_delivered', 'assistances.date_requested')} END) as avg_days_to_deliver")
            ->selectRaw("AVG(CASE WHEN first_verified.verified_at IS NOT NULL AND assistances.date_requested IS NOT NULL THEN {$firstVerifiedAt} END) as avg_days_to_verify")
            ->toBase()
            ->first();

        $programCounts = Program::query()
            ->where('department_id', $department->id)
            ->selectRaw('COUNT(CASE WHEN is_closed = 0 THEN 1 END) as active_programs')
            ->selectRaw('COUNT(CASE WHEN is_closed = 1 THEN 1 END) as closed_programs')
            ->toBase()
            ->first();

        $beneficiaryFrequency = DB::query()
            ->fromSub(
                (clone $this->filteredAssistanceQuery($department, $filters))
                    ->whereNotNull('assistances.beneficiary_id')
                    ->select('assistances.beneficiary_id')
                    ->selectRaw('COUNT(DISTINCT assistances.id) as request_count')
                    ->groupBy('assistances.beneficiary_id')
                    ->toBase(),
                'beneficiary_counts',
            )
            ->selectRaw('COALESCE(SUM(CASE WHEN request_count > 1 THEN 1 ELSE 0 END), 0) as repeat_beneficiaries')
            ->selectRaw('COALESCE(SUM(CASE WHEN request_count = 1 THEN 1 ELSE 0 END), 0) as one_time_beneficiaries')
            ->first();

        $uniqueBeneficiaries = (int) ($stats->unique_beneficiaries ?? 0);
        $totalRequests = (int) ($stats->total_requests ?? 0);

        return [
            'total_requests' => $totalRequests,
            'delivered_requests' => (int) ($stats->delivered_requests ?? 0),
            'total_delivered_items' => $this->sumDeliveredItems($department, $filters),
            'active_programs' => (int) ($programCounts->active_programs ?? 0),
            'in_progress_requests' => (int) ($stats->in_progress_requests ?? 0),
            'denied_requests' => (int) ($stats->denied_requests ?? 0),
            'unique_beneficiaries' => $uniqueBeneficiaries,
            'closed_programs' => (int) ($programCounts->closed_programs ?? 0),
            'avg_days_to_deliver' => $stats->avg_days_to_deliver !== null
                ? round((float) $stats->avg_days_to_deliver, 1)
                : null,
            'avg_days_to_verify' => $stats->avg_days_to_verify !== null
                ? round((float) $stats->avg_days_to_verify, 1)
                : null,
            'repeat_beneficiaries' => (int) ($beneficiaryFrequency->repeat_beneficiaries ?? 0),
            'one_time_beneficiaries' => (int) ($beneficiaryFrequency->one_time_beneficiaries ?? 0),
            'avg_requests_per_beneficiary' => $uniqueBeneficiaries > 0
                ? round($totalRequests / $uniqueBeneficiaries, 2)
                : 0.0,
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
     * @return list<array{item: string, unit: string, count: int, quantity: int}>
     */
    public function deliveredItemsChart(Department $department, array $filters): array
    {
        $itemTable = (new Item)->getTable();

        return DB::table((new AssistanceItem)->getTable().' as ai')
            ->joinSub(
                $this->filteredDeliveredAssistanceIdsSubquery($department, $filters),
                'delivered_assistances',
                'delivered_assistances.id',
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
            ->selectRaw('COUNT(ai.id) as count')
            ->selectRaw('COALESCE(SUM(ai.quantity), 0) as quantity')
            ->groupBy('items.name', 'ium.name')
            ->orderByDesc('quantity')
            ->limit(10)
            ->get()
            ->map(static fn ($row): array => [
                'item' => (string) $row->item,
                'unit' => (string) ($row->unit ?? '—'),
                'count' => (int) $row->count,
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
     *     in_progress: int,
     *     denied: int,
     *     delivery_rate: float
     * }>
     */
    public function programsTable(Department $department, array $filters): array
    {
        $statusExpression = $this->resolvedStatusExpression();
        $deliveredSql = $this->isDeliveredSql();
        $terminalList = implode("','", self::TERMINAL_STATUSES);

        $programQuery = Program::query()
            ->where('department_id', $department->id)
            ->orderByDesc('id');

        $selectedPrograms = $filters['program'] ?? [];

        if ($selectedPrograms !== []) {
            $programQuery->whereIn('id', $selectedPrograms);
        }

        $programs = $programQuery
            ->limit(self::PROGRAMS_TABLE_LIMIT)
            ->get(['id', 'name', 'is_closed', 'is_organization']);

        if ($programs->isEmpty()) {
            return [];
        }

        $programIds = $programs->pluck('id')->all();

        $demographicFilters = $filters;
        unset($demographicFilters['program']);
        $demographicFilters['program'] = $programIds;

        $statsByProgramId = (clone $this->filteredAssistanceQuery($department, $demographicFilters))
            ->select('programs.id')
            ->selectRaw('COUNT(DISTINCT assistances.id) as total_requests')
            ->selectRaw("COUNT(DISTINCT CASE WHEN {$deliveredSql} THEN assistances.id END) as delivered")
            ->selectRaw("COUNT(DISTINCT CASE WHEN {$statusExpression} NOT IN ('{$terminalList}') THEN assistances.id END) as in_progress")
            ->selectRaw("COUNT(DISTINCT CASE WHEN {$statusExpression} = 'Denied' THEN assistances.id END) as denied")
            ->groupBy('programs.id')
            ->toBase()
            ->get()
            ->keyBy('id');

        return $programs
            ->map(function (Program $program) use ($statsByProgramId): array {
                $stats = $statsByProgramId->get($program->id);
                $total = (int) ($stats->total_requests ?? 0);
                $delivered = (int) ($stats->delivered ?? 0);

                return [
                    'id' => $program->id,
                    'name' => $program->name,
                    'type' => $program->is_organization ? 'organization' : 'individual',
                    'status' => $program->is_closed ? 'closed' : 'open',
                    'total_requests' => $total,
                    'delivered' => $delivered,
                    'in_progress' => (int) ($stats->in_progress ?? 0),
                    'denied' => (int) ($stats->denied ?? 0),
                    'delivery_rate' => $total > 0
                        ? round(($delivered / $total) * 100, 1)
                        : 0.0,
                ];
            })
            ->values()
            ->all();
    }

    /**
     * @return array{
     *     year: list<array{label: string, value: string}>,
     *     quarter: list<array{label: string, value: string}>,
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
        return Cache::remember(
            "dashboard.filter_options.{$department->id}",
            now()->addMinutes(10),
            function () use ($department): array {
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

                $years = Assistance::query()
                    ->join('programs', 'programs.id', '=', 'assistances.program_id')
                    ->where('programs.department_id', $department->id)
                    ->whereNotNull('assistances.date_requested')
                    ->selectRaw('DISTINCT YEAR(assistances.date_requested) as year')
                    ->orderByDesc('year')
                    ->toBase()
                    ->pluck('year')
                    ->map(static fn (mixed $year): array => [
                        'label' => (string) (int) $year,
                        'value' => (string) (int) $year,
                    ])
                    ->values()
                    ->all();

                if ($years === []) {
                    $currentYear = now()->year;
                    $years = [
                        ['label' => (string) $currentYear, 'value' => (string) $currentYear],
                    ];
                }

                $yesNo = [
                    ['label' => 'Yes', 'value' => 'true'],
                    ['label' => 'No', 'value' => 'false'],
                ];

                return [
                    'year' => $years,
                    'quarter' => [
                        ['label' => 'Q1 (Jan–Mar)', 'value' => '1'],
                        ['label' => 'Q2 (Apr–Jun)', 'value' => '2'],
                        ['label' => 'Q3 (Jul–Sep)', 'value' => '3'],
                        ['label' => 'Q4 (Oct–Dec)', 'value' => '4'],
                    ],
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
            },
        );
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return list<array{type: string, label: string, count: int}>
     */
    public function beneficiaryTypeChart(Department $department, array $filters): array
    {
        $individualClass = Individual::class;
        $organizationClass = Organization::class;

        return (clone $this->filteredAssistanceQuery($department, $filters))
            ->selectRaw('beneficiaries.beneficiable_type as beneficiable_type')
            ->selectRaw('COUNT(DISTINCT assistances.id) as count')
            ->whereNotNull('beneficiaries.beneficiable_type')
            ->groupBy('beneficiaries.beneficiable_type')
            ->orderByDesc('count')
            ->toBase()
            ->get()
            ->map(static function ($row) use ($individualClass, $organizationClass): array {
                $type = match ((string) $row->beneficiable_type) {
                    $individualClass => 'individual',
                    $organizationClass => 'organization',
                    default => 'other',
                };

                $label = match ($type) {
                    'individual' => 'Individual',
                    'organization' => 'Organization',
                    default => 'Other',
                };

                return [
                    'type' => $type,
                    'label' => $label,
                    'count' => (int) $row->count,
                ];
            })
            ->values()
            ->all();
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return array{
     *     sex: list<array{label: string, count: int}>,
     *     pwd: list<array{label: string, count: int}>,
     *     four_ps: list<array{label: string, count: int}>,
     *     solo_parent: list<array{label: string, count: int}>,
     *     indigenous: list<array{label: string, count: int}>,
     *     age: list<array{label: string, count: int}>,
     *     civil_status: list<array{label: string, count: int}>
     * }
     */
    public function demographics(Department $department, array $filters): array
    {
        $base = (clone $this->filteredAssistanceQuery($department, $filters))
            ->where('beneficiaries.beneficiable_type', Individual::class)
            ->whereNotNull('individuals.id');

        $sex = (clone $base)
            ->selectRaw("COALESCE(NULLIF(individuals.sex, ''), 'Unspecified') as label")
            ->selectRaw('COUNT(DISTINCT assistances.id) as count')
            ->groupByRaw("COALESCE(NULLIF(individuals.sex, ''), 'Unspecified')")
            ->orderByDesc('count')
            ->toBase()
            ->get()
            ->map(static fn ($row): array => [
                'label' => (string) $row->label,
                'count' => (int) $row->count,
            ])
            ->values()
            ->all();

        $ageExpression = "CASE
            WHEN individuals.birthday IS NULL THEN 'Unspecified'
            WHEN TIMESTAMPDIFF(YEAR, individuals.birthday, CURDATE()) < 18 THEN 'Under 18'
            WHEN TIMESTAMPDIFF(YEAR, individuals.birthday, CURDATE()) BETWEEN 18 AND 29 THEN '18-29'
            WHEN TIMESTAMPDIFF(YEAR, individuals.birthday, CURDATE()) BETWEEN 30 AND 44 THEN '30-44'
            WHEN TIMESTAMPDIFF(YEAR, individuals.birthday, CURDATE()) BETWEEN 45 AND 59 THEN '45-59'
            ELSE '60+'
        END";

        $age = (clone $base)
            ->selectRaw("{$ageExpression} as label")
            ->selectRaw('COUNT(DISTINCT assistances.id) as count')
            ->groupByRaw($ageExpression)
            ->orderByDesc('count')
            ->toBase()
            ->get()
            ->map(static fn ($row): array => [
                'label' => (string) $row->label,
                'count' => (int) $row->count,
            ])
            ->values()
            ->all();

        $civilStatus = (clone $base)
            ->leftJoin('civil_statuses', 'civil_statuses.id', '=', 'individuals.civil_status_id')
            ->selectRaw("COALESCE(NULLIF(civil_statuses.name, ''), 'Unspecified') as label")
            ->selectRaw('COUNT(DISTINCT assistances.id) as count')
            ->groupByRaw("COALESCE(NULLIF(civil_statuses.name, ''), 'Unspecified')")
            ->orderByDesc('count')
            ->toBase()
            ->get()
            ->map(static fn ($row): array => [
                'label' => (string) $row->label,
                'count' => (int) $row->count,
            ])
            ->values()
            ->all();

        $booleanTotals = (clone $base)
            ->selectRaw('COUNT(DISTINCT CASE WHEN individuals.pwd = 1 THEN assistances.id END) as pwd_yes')
            ->selectRaw('COUNT(DISTINCT CASE WHEN individuals.pwd = 0 THEN assistances.id END) as pwd_no')
            ->selectRaw('COUNT(DISTINCT CASE WHEN individuals.is_4ps_beneficiary = 1 THEN assistances.id END) as four_ps_yes')
            ->selectRaw('COUNT(DISTINCT CASE WHEN individuals.is_4ps_beneficiary = 0 THEN assistances.id END) as four_ps_no')
            ->selectRaw('COUNT(DISTINCT CASE WHEN individuals.is_solo_parent = 1 THEN assistances.id END) as solo_parent_yes')
            ->selectRaw('COUNT(DISTINCT CASE WHEN individuals.is_solo_parent = 0 THEN assistances.id END) as solo_parent_no')
            ->selectRaw('COUNT(DISTINCT CASE WHEN individuals.indigenous = 1 THEN assistances.id END) as indigenous_yes')
            ->selectRaw('COUNT(DISTINCT CASE WHEN individuals.indigenous = 0 THEN assistances.id END) as indigenous_no')
            ->toBase()
            ->first();

        return [
            'sex' => $sex,
            'age' => $age,
            'civil_status' => $civilStatus,
            'pwd' => $this->booleanBreakdownFromTotals($booleanTotals, 'pwd'),
            'four_ps' => $this->booleanBreakdownFromTotals($booleanTotals, 'four_ps'),
            'solo_parent' => $this->booleanBreakdownFromTotals($booleanTotals, 'solo_parent'),
            'indigenous' => $this->booleanBreakdownFromTotals($booleanTotals, 'indigenous'),
        ];
    }

    /**
     * Daily request counts for the selected filters (client aggregates for week/month).
     *
     * @param  array<string, mixed>  $filters
     * @return list<array{date: string, count: int}>
     */
    public function requestsTrend(Department $department, array $filters): array
    {
        return (clone $this->filteredAssistanceQuery($department, $filters))
            ->whereNotNull('assistances.date_requested')
            ->selectRaw('DATE(assistances.date_requested) as request_date')
            ->selectRaw('COUNT(DISTINCT assistances.id) as count')
            ->groupByRaw('DATE(assistances.date_requested)')
            ->orderBy('request_date')
            ->toBase()
            ->get()
            ->map(static fn ($row): array => [
                'date' => (string) $row->request_date,
                'count' => (int) $row->count,
            ])
            ->values()
            ->all();
    }

    /**
     * Operational insights: backlog aging and item fulfillment.
     *
     * @param  array<string, mixed>  $filters
     * @return array{
     *     backlog_aging: list<array{label: string, count: int}>,
     *     pending_items: int,
     *     received_items: int,
     *     distinct_barangays: int
     * }
     */
    public function insights(Department $department, array $filters): array
    {
        $statusExpression = $this->resolvedStatusExpression();
        $terminalList = implode("','", self::TERMINAL_STATUSES);

        $agingExpression = "CASE
            WHEN assistances.date_requested IS NULL THEN 'No request date'
            WHEN DATEDIFF(CURDATE(), assistances.date_requested) <= 7 THEN '0-7 days'
            WHEN DATEDIFF(CURDATE(), assistances.date_requested) <= 30 THEN '8-30 days'
            WHEN DATEDIFF(CURDATE(), assistances.date_requested) <= 90 THEN '31-90 days'
            ELSE '90+ days'
        END";

        $backlogAging = (clone $this->filteredAssistanceQuery($department, $filters))
            ->whereRaw("{$statusExpression} NOT IN ('{$terminalList}')")
            ->selectRaw("{$agingExpression} as label")
            ->selectRaw('COUNT(DISTINCT assistances.id) as count')
            ->groupByRaw($agingExpression)
            ->orderByDesc('count')
            ->toBase()
            ->get()
            ->map(static fn ($row): array => [
                'label' => (string) $row->label,
                'count' => (int) $row->count,
            ])
            ->values()
            ->all();

        $itemStats = DB::table((new AssistanceItem)->getTable().' as ai')
            ->joinSub(
                $this->filteredAssistanceQuery($department, $filters)
                    ->select('assistances.id')
                    ->distinct()
                    ->toBase(),
                'scoped_assistances',
                'scoped_assistances.id',
                '=',
                'ai.assistance_id',
            )
            ->whereNull('ai.deleted_at')
            ->selectRaw('SUM(CASE WHEN ai.is_received = 1 THEN 1 ELSE 0 END) as received_items')
            ->selectRaw('SUM(CASE WHEN ai.is_received = 0 OR ai.is_received IS NULL THEN 1 ELSE 0 END) as pending_items')
            ->first();

        $distinctBarangays = (clone $this->filteredAssistanceQuery($department, $filters))
            ->leftJoin('organizations', function ($join): void {
                $join->on('beneficiaries.beneficiable_id', '=', 'organizations.id')
                    ->where('beneficiaries.beneficiable_type', '=', Organization::class)
                    ->whereNull('organizations.deleted_at');
            })
            ->leftJoin('address_barangays as ab_ind', 'ab_ind.id', '=', 'individuals.address_barangay_id')
            ->leftJoin('address_barangays as ab_org', 'ab_org.id', '=', 'organizations.address_barangay_id')
            ->selectRaw('COUNT(DISTINCT COALESCE(ab_ind.id, ab_org.id)) as barangay_count')
            ->toBase()
            ->first();

        return [
            'backlog_aging' => $backlogAging,
            'pending_items' => (int) ($itemStats->pending_items ?? 0),
            'received_items' => (int) ($itemStats->received_items ?? 0),
            'distinct_barangays' => (int) ($distinctBarangays->barangay_count ?? 0),
        ];
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return list<array{barangay: string, count: int}>
     */
    public function topBarangays(Department $department, array $filters): array
    {
        return (clone $this->filteredAssistanceQuery($department, $filters))
            ->leftJoin('organizations', function ($join): void {
                $join->on('beneficiaries.beneficiable_id', '=', 'organizations.id')
                    ->where('beneficiaries.beneficiable_type', '=', Organization::class)
                    ->whereNull('organizations.deleted_at');
            })
            ->leftJoin('address_barangays as ab_ind', 'ab_ind.id', '=', 'individuals.address_barangay_id')
            ->leftJoin('address_barangays as ab_org', 'ab_org.id', '=', 'organizations.address_barangay_id')
            ->selectRaw("COALESCE(NULLIF(ab_ind.name, ''), NULLIF(ab_org.name, ''), 'Unspecified') as barangay")
            ->selectRaw('COUNT(DISTINCT assistances.id) as count')
            ->groupByRaw("COALESCE(NULLIF(ab_ind.name, ''), NULLIF(ab_org.name, ''), 'Unspecified')")
            ->orderByDesc('count')
            ->limit(8)
            ->toBase()
            ->get()
            ->map(static fn ($row): array => [
                'barangay' => (string) $row->barangay,
                'count' => (int) $row->count,
            ])
            ->values()
            ->all();
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return list<array{label: string, count: int}>
     */
    public function modeOfRequestChart(Department $department, array $filters): array
    {
        return (clone $this->filteredAssistanceQuery($department, $filters))
            ->leftJoin('mode_of_requests', 'mode_of_requests.id', '=', 'assistances.mode_of_request_id')
            ->selectRaw("COALESCE(NULLIF(mode_of_requests.name, ''), 'Unspecified') as label")
            ->selectRaw('COUNT(DISTINCT assistances.id) as count')
            ->groupByRaw("COALESCE(NULLIF(mode_of_requests.name, ''), 'Unspecified')")
            ->orderByDesc('count')
            ->toBase()
            ->get()
            ->map(static fn ($row): array => [
                'label' => (string) $row->label,
                'count' => (int) $row->count,
            ])
            ->values()
            ->all();
    }

    /**
     * @return list<array{label: string, count: int}>
     */
    private function booleanBreakdownFromTotals(?object $totals, string $prefix): array
    {
        $rows = [
            ['label' => 'Yes', 'count' => (int) ($totals?->{"{$prefix}_yes"} ?? 0)],
            ['label' => 'No', 'count' => (int) ($totals?->{"{$prefix}_no"} ?? 0)],
        ];

        usort($rows, static fn (array $left, array $right): int => $right['count'] <=> $left['count']);

        return array_values(array_filter(
            $rows,
            static fn (array $row): bool => $row['count'] > 0,
        ));
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    private function sumDeliveredItems(Department $department, array $filters): int
    {
        return (int) DB::table((new AssistanceItem)->getTable().' as ai')
            ->joinSub(
                $this->filteredDeliveredAssistanceIdsSubquery($department, $filters),
                'delivered_assistances',
                'delivered_assistances.id',
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
    private function filteredDeliveredAssistanceIdsSubquery(Department $department, array $filters): QueryBuilder
    {
        $deliveredSql = $this->isDeliveredSql();

        return $this->filteredAssistanceQuery($department, $filters)
            ->whereRaw($deliveredSql)
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
        return 'assistances.was_delivered = 1';
    }

    private function resolvedStatusExpression(): string
    {
        return "COALESCE(rs.name, 'Unrequested')";
    }

    private function dateDiffExpression(string $endColumn, string $startColumn): string
    {
        if (DB::getDriverName() === 'sqlite') {
            return "CAST(julianday({$endColumn}) - julianday({$startColumn}) AS INTEGER)";
        }

        return "DATEDIFF({$endColumn}, {$startColumn})";
    }

    private function firstVerifiedAtSubquery(): QueryBuilder
    {
        return DB::table('assistance_request_sub_status as arss')
            ->join('request_sub_statuses as rss', 'rss.id', '=', 'arss.request_sub_status_id')
            ->whereNull('arss.deleted_at')
            ->where('rss.name', 'Verified')
            ->groupBy('arss.assistance_id')
            ->select('arss.assistance_id')
            ->selectRaw('MIN(arss.recorded_at) as verified_at');
    }
}
