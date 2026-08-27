<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use App\Http\Requests\User\Dashboard\IndexRequest;
use App\Models\Department;
use App\Services\User\DashboardService;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function __construct(
        private DashboardService $dashboardService,
    ) {}

    /**
     * Display the department dashboard.
     */
    public function index(IndexRequest $request, Department $department): Response
    {
        $filters = $request->filters();

        return Inertia::render('user/dashboard/index', [
            'department' => $department->only(['id', 'name', 'slug']),
            'summary' => Inertia::defer(
                fn () => $this->dashboardService->summary($department, $filters),
                'kpis',
            ),
            'filters' => Inertia::defer(
                fn () => $this->dashboardService->serializeFilters($filters),
                'filters',
            ),
            'filterOptions' => Inertia::defer(
                fn () => $this->dashboardService->filterOptions($department),
                'filters',
            ),
            'requestStatusChart' => Inertia::defer(
                fn () => $this->dashboardService->requestStatusChart($department, $filters),
                'charts',
            ),
            'deliveredItemsChart' => Inertia::defer(
                fn () => $this->dashboardService->deliveredItemsChart($department, $filters),
                'charts',
            ),
            'unspscReleasedChart' => Inertia::defer(
                fn () => $this->dashboardService->unspscReleasedChart($department, $filters),
                'charts',
            ),
            'beneficiaryTypeChart' => Inertia::defer(
                fn () => $this->dashboardService->beneficiaryTypeChart($department, $filters),
                'charts',
            ),
            'demographics' => Inertia::defer(
                fn () => $this->dashboardService->demographics($department, $filters),
                'demographics',
            ),
            'requestsTrend' => Inertia::defer(
                fn () => $this->dashboardService->requestsTrend($department, $filters),
                'charts',
            ),
            'insights' => Inertia::defer(
                fn () => $this->dashboardService->insights($department, $filters),
                'insights',
            ),
            'topBarangays' => Inertia::defer(
                fn () => $this->dashboardService->topBarangays($department, $filters),
                'insights',
            ),
            'modeOfRequestChart' => Inertia::defer(
                fn () => $this->dashboardService->modeOfRequestChart($department, $filters),
                'insights',
            ),
            'programsTable' => Inertia::defer(
                fn () => $this->dashboardService->programsTable($department, $filters),
                'programs',
            ),
        ]);
    }
}
