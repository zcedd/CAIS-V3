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
            'summary' => fn () => $this->dashboardService->summary($department, $filters),
            'filters' => $this->dashboardService->serializeFilters($filters),
            'filterOptions' => fn () => $this->dashboardService->filterOptions($department),
            'requestStatusChart' => Inertia::defer(
                fn () => $this->dashboardService->requestStatusChart($department, $filters),
                'dashboard',
            ),
            'deliveredItemsChart' => Inertia::defer(
                fn () => $this->dashboardService->deliveredItemsChart($department, $filters),
                'dashboard',
            ),
            'programsTable' => Inertia::defer(
                fn () => $this->dashboardService->programsTable($department, $filters),
                'dashboard',
            ),
        ]);
    }
}
