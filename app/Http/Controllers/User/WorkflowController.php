<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use App\Http\Requests\User\Workflow\IndexRequest;
use App\Http\Requests\User\Workflow\StoreRequest;
use App\Http\Requests\User\Workflow\UpdateRequest;
use App\Models\Department;
use App\Models\Workflow;
use App\Services\User\WorkflowService;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class WorkflowController extends Controller
{
    public function __construct(
        private WorkflowService $workflowService,
    ) {}

    public function index(IndexRequest $request, Department $department): Response
    {
        return Inertia::render('user/workflows/index', [
            'department' => $department->only(['id', 'name', 'slug']),
            'workflows' => $this->workflowService->listForDepartment($department),
            'statuses' => $this->workflowService->activeStatuses(),
            'reasons' => $this->workflowService->activeReasons(),
            'can_create' => $request->user()?->can('create', [Workflow::class, $department]) ?? false,
        ]);
    }

    public function store(StoreRequest $request, Department $department): RedirectResponse
    {
        $this->workflowService->create($department, $request->validated());

        return redirect()
            ->back()
            ->with('success', 'Workflow created.');
    }

    public function update(
        UpdateRequest $request,
        Department $department,
        Workflow $workflow,
    ): RedirectResponse {
        $this->workflowService->update($workflow, $request->validated());

        return redirect()
            ->back()
            ->with('success', 'Workflow updated.');
    }
}
