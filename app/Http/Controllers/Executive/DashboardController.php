<?php

namespace App\Http\Controllers\Executive;

use App\Http\Controllers\Controller;
use App\Http\Requests\Executive\Dashboard\IndexRequest;
use App\Services\User\DashboardService;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function __construct(
        private DashboardService $dashboardService,
    ) {}

    public function index(IndexRequest $request): Response
    {
        $filters = $request->filters();

        return Inertia::render('executive/dashboard/index', [
            'summary' => Inertia::defer(
                fn () => $this->dashboardService->summary(null, $filters),
                'kpis',
            ),
            'filters' => Inertia::defer(
                fn () => $this->dashboardService->serializeFilters($filters),
                'filters',
            ),
            'filterOptions' => Inertia::defer(
                fn () => $this->dashboardService->filterOptions(null, $filters),
                'filters',
            ),
            'requestStatusChart' => Inertia::defer(
                fn () => $this->dashboardService->requestStatusChart(null, $filters),
                'charts',
            ),
            'deliveredItemsChart' => Inertia::defer(
                fn () => $this->dashboardService->deliveredItemsChart(null, $filters),
                'charts',
            ),
            'unspscReleasedChart' => Inertia::defer(
                fn () => $this->dashboardService->unspscReleasedChart(null, $filters),
                'charts',
            ),
            'beneficiaryTypeChart' => Inertia::defer(
                fn () => $this->dashboardService->beneficiaryTypeChart(null, $filters),
                'charts',
            ),
            'demographics' => Inertia::defer(
                fn () => $this->dashboardService->demographics(null, $filters),
                'demographics',
            ),
            'requestsTrend' => Inertia::defer(
                fn () => $this->dashboardService->requestsTrend(null, $filters),
                'charts',
            ),
            'insights' => Inertia::defer(
                fn () => $this->dashboardService->insights(null, $filters),
                'insights',
            ),
            'topBarangays' => Inertia::defer(
                fn () => $this->dashboardService->topBarangays(null, $filters),
                'insights',
            ),
            'modeOfRequestChart' => Inertia::defer(
                fn () => $this->dashboardService->modeOfRequestChart(null, $filters),
                'insights',
            ),
            'programsTable' => Inertia::defer(
                fn () => $this->dashboardService->programsTable(null, $filters),
                'programs',
            ),
        ]);
    }
}
