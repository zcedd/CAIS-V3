<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use App\Http\Requests\User\Item\DestroyRequest;
use App\Http\Requests\User\Item\IndexRequest;
use App\Http\Requests\User\Item\StoreRequest;
use App\Http\Requests\User\Item\UpdateRequest;
use App\Models\Department;
use App\Models\Item;
use App\Services\User\ItemService;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class ItemController extends Controller
{
    public function __construct(
        private ItemService $itemService,
    ) {}

    /**
     * Display items belonging to the authenticated user's department.
     */
    public function index(IndexRequest $request, Department $department): Response
    {
        $items = $this->itemService->paginateForDepartment(
            $department,
            $request->search(),
            $request->sort(),
            $request->direction(),
            $request->perPage(),
        );

        return Inertia::render('user/items/index', [
            'items' => $items,
            'department' => $department->only(['id', 'name', 'slug']),
            'search' => $request->search(),
            'sort' => $request->sort(),
            'direction' => $request->direction(),
            'unit_measurements' => $this->itemService->unitMeasurementsForSelect(),
        ]);
    }

    /**
     * Store a newly created item for the authenticated user's department.
     */
    public function store(StoreRequest $request, Department $department): RedirectResponse
    {
        $this->itemService->create($department, $request->validated());

        return redirect()
            ->back()
            ->with('success', 'Item created successfully.');
    }

    /**
     * Update an item for the authenticated user's department.
     */
    public function update(UpdateRequest $request, Item $item): RedirectResponse
    {
        $this->itemService->update($item, $request->validated());

        return redirect()
            ->back()
            ->with('success', 'Item updated successfully.');
    }

    /**
     * Remove an item from the authenticated user's department.
     */
    public function destroy(DestroyRequest $request, Item $item): RedirectResponse
    {
        $this->itemService->delete($item);

        return redirect()
            ->back()
            ->with('success', 'Item deleted successfully.');
    }
}
