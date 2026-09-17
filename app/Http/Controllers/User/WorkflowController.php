<?php

namespace App\Http\Controllers\User;

use App\Actions\Admin\CreateWorkflowVersion;
use App\Enums\RoleName;
use App\Http\Controllers\Controller;
use App\Http\Requests\User\Workflow\ActivateRequest;
use App\Http\Requests\User\Workflow\DeactivateRequest;
use App\Http\Requests\User\Workflow\IndexRequest;
use App\Http\Requests\User\Workflow\PublishRequest;
use App\Http\Requests\User\Workflow\StoreRequest;
use App\Http\Requests\User\Workflow\UpdateRequest;
use App\Http\Requests\User\Workflow\VersionRequest;
use App\Models\Department;
use App\Models\Workflow;
use App\Services\Admin\WorkflowService as AdminWorkflowService;
use App\Services\User\WorkflowService;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class WorkflowController extends Controller
{
    public function __construct(
        private WorkflowService $workflowService,
        private AdminWorkflowService $adminWorkflowService,
        private CreateWorkflowVersion $createWorkflowVersion,
    ) {}

    public function index(IndexRequest $request, Department $department): Response
    {
        $user = $request->user();

        return Inertia::render('user/workflows/index', [
            'department' => $department->only(['id', 'name', 'slug']),
            'workflows' => $this->workflowService->listForDepartment($department),
            'statuses' => $this->workflowService->activeStatuses(),
            'reasons' => $this->workflowService->activeReasons(),
            'staff_options' => $this->workflowService->departmentStaffForSelect($department),
            'role_options' => RoleName::options(),
            'can_create' => $user?->can('create', [Workflow::class, $department]) ?? false,
            'can' => $this->adminWorkflowService->abilities($user, null, $department),
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

    public function publish(
        PublishRequest $request,
        Department $department,
        Workflow $workflow,
    ): RedirectResponse {
        $this->adminWorkflowService->publish($workflow);

        return redirect()
            ->back()
            ->with('success', 'Workflow published.');
    }

    public function activate(
        ActivateRequest $request,
        Department $department,
        Workflow $workflow,
    ): RedirectResponse {
        $this->adminWorkflowService->activate($workflow);

        return redirect()
            ->back()
            ->with('success', 'Workflow activated.');
    }

    public function deactivate(
        DeactivateRequest $request,
        Department $department,
        Workflow $workflow,
    ): RedirectResponse {
        $this->adminWorkflowService->deactivate($workflow);

        return redirect()
            ->back()
            ->with('success', 'Workflow deactivated.');
    }

    public function version(
        VersionRequest $request,
        Department $department,
        Workflow $workflow,
    ): RedirectResponse {
        ($this->createWorkflowVersion)($workflow);

        return redirect()
            ->back()
            ->with('success', 'Workflow version created as draft.');
    }
}
