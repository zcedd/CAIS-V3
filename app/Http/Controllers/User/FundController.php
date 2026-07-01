<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use App\Http\Requests\User\Fund\DestroyRequest;
use App\Http\Requests\User\Fund\IndexRequest;
use App\Http\Requests\User\Fund\StoreRequest;
use App\Http\Requests\User\Fund\UpdateRequest;
use App\Models\Department;
use App\Models\Fund;
use App\Services\User\FundService;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class FundController extends Controller
{
    public function __construct(
        private FundService $fundService,
    ) {}

    /**
     * Display funds belonging to the authenticated user's department.
     */
    public function index(IndexRequest $request, Department $department): Response
    {
        $search = $request->search();
        $statuses = $request->statuses();

        $funds = $this->fundService->paginateForDepartment(
            $department,
            $search,
            $statuses,
        );

        return Inertia::render('user/funds/index', [
            'funds' => Inertia::scroll($funds),
            'department' => $department->only(['id', 'name', 'slug']),
            'search' => $search,
            'status' => $statuses,
        ]);
    }

    /**
     * Store a newly created fund for the authenticated user's department.
     */
    public function store(StoreRequest $request, Department $department): RedirectResponse
    {
        $this->fundService->create($department, $request->validated());

        return redirect()
            ->back()
            ->with('success', 'Fund created successfully.');
    }

    /**
     * Update a fund for the authenticated user's department.
     */
    public function update(
        UpdateRequest $request,
        Department $department,
        Fund $fund,
    ): RedirectResponse {
        $this->fundService->update($fund, $request->validated());

        return redirect()
            ->back()
            ->with('success', 'Fund updated successfully.');
    }

    /**
     * Remove a fund from the authenticated user's department.
     */
    public function destroy(
        DestroyRequest $request,
        Department $department,
        Fund $fund,
    ): RedirectResponse {
        $this->fundService->delete($fund);

        return redirect()
            ->back()
            ->with('success', 'Fund deleted successfully.');
    }
}
