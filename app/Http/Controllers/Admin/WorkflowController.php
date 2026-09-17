<?php

namespace App\Http\Controllers\Admin;

use App\Actions\Admin\ReassignWorkflowTask;
use App\Enums\RoleName;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Workflow\ActivateRequest;
use App\Http\Requests\Admin\Workflow\AssignProgramsRequest;
use App\Http\Requests\Admin\Workflow\DeactivateRequest;
use App\Http\Requests\Admin\Workflow\DestroyRequest;
use App\Http\Requests\Admin\Workflow\DuplicateRequest;
use App\Http\Requests\Admin\Workflow\IndexRequest;
use App\Http\Requests\Admin\Workflow\OverrideTaskRequest;
use App\Http\Requests\Admin\Workflow\PublishRequest;
use App\Http\Requests\Admin\Workflow\ReassignTaskRequest;
use App\Http\Requests\Admin\Workflow\ShowRequest;
use App\Http\Requests\Admin\Workflow\StoreRequest;
use App\Http\Requests\Admin\Workflow\UpdateRequest;
use App\Http\Requests\Admin\Workflow\VersionRequest;
use App\Models\Assistance;
use App\Models\Department;
use App\Models\User;
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
        private ReassignWorkflowTask $reassignWorkflowTask,
    ) {}

    public function index(IndexRequest $request): Response
    {
        $user = $request->user();
        abort_unless($user instanceof User, 403);
        $departments = Department::query()
            ->orderBy('name')
            ->get(['id', 'name', 'slug']);

        $slug = $request->departmentSlug();
        $department = $slug === ''
            ? null
            : $departments->firstWhere('slug', $slug);

        return Inertia::render('admin/workflows/index', [
            'departments' => $departments,
            'department' => $department?->only(['id', 'name', 'slug']),
            'workflows' => $this->adminWorkflowService->listSummaries($department),
            'can' => $this->adminWorkflowService->abilities($user, null, $department),
        ]);
    }

    public function create(IndexRequest $request): Response
    {
        $user = $request->user();
        abort_unless($user instanceof User, 403);
        $departments = Department::query()
            ->orderBy('name')
            ->get(['id', 'name', 'slug']);

        return Inertia::render('admin/workflows/create', [
            'departments' => $departments,
            'can' => $this->adminWorkflowService->abilities($user),
        ]);
    }

    public function store(StoreRequest $request): RedirectResponse
    {
        $department = Department::query()->findOrFail($request->integer('department_id'));
        $workflow = $this->adminWorkflowService->create($department, $request->validated());

        return redirect()
            ->route('admin.workflows.show', $workflow)
            ->with('success', 'Workflow created as a draft.');
    }

    public function show(ShowRequest $request, Workflow $workflow): Response
    {
        $user = $request->user();
        abort_unless($user instanceof User, 403);
        $department = $workflow->department ?? Department::query()->findOrFail($workflow->department_id);

        return Inertia::render('admin/workflows/show', [
            'department' => $department->only(['id', 'name', 'slug']),
            'departments' => Department::query()->orderBy('name')->get(['id', 'name', 'slug']),
            'workflow' => $this->workflowService->serialize($workflow->load(['steps.transitions', 'programs'])),
            'statuses' => $this->workflowService->activeStatuses(),
            'reasons' => $this->workflowService->activeReasons(),
            'staff_options' => $this->workflowService->departmentStaffForSelect($department),
            'role_options' => RoleName::options(),
            'department_programs' => $this->adminWorkflowService->departmentPrograms($department),
            'can' => $this->adminWorkflowService->abilities($user, $workflow, $department),
        ]);
    }

    public function update(UpdateRequest $request, Workflow $workflow): RedirectResponse
    {
        $this->adminWorkflowService->update($workflow, $request->validated());

        return redirect()
            ->back()
            ->with('success', 'Workflow updated.');
    }

    public function destroy(DestroyRequest $request, Workflow $workflow): RedirectResponse
    {
        $result = $this->adminWorkflowService->delete($workflow);

        return redirect()
            ->route('admin.workflows.index', [
                'department' => $workflow->department?->slug,
            ])
            ->with(
                'success',
                $result === 'deactivated'
                    ? 'Workflow is in use, so it was deactivated instead of deleted.'
                    : 'Workflow deleted.',
            );
    }

    public function publish(PublishRequest $request, Workflow $workflow): RedirectResponse
    {
        $this->adminWorkflowService->publish($workflow);

        return redirect()
            ->back()
            ->with('success', 'Workflow published.');
    }

    public function activate(ActivateRequest $request, Workflow $workflow): RedirectResponse
    {
        $this->adminWorkflowService->activate($workflow);

        return redirect()
            ->back()
            ->with('success', 'Workflow activated.');
    }

    public function deactivate(DeactivateRequest $request, Workflow $workflow): RedirectResponse
    {
        $this->adminWorkflowService->deactivate($workflow);

        return redirect()
            ->back()
            ->with('success', 'Workflow deactivated.');
    }

    public function duplicate(DuplicateRequest $request, Workflow $workflow): RedirectResponse
    {
        $copy = $this->adminWorkflowService->duplicate(
            $workflow,
            $request->validated('name'),
            $request->validated('code'),
        );

        return redirect()
            ->route('admin.workflows.show', $copy)
            ->with('success', 'Workflow duplicated as a draft.');
    }

    public function version(VersionRequest $request, Workflow $workflow): RedirectResponse
    {
        $copy = $this->adminWorkflowService->version($workflow);

        return redirect()
            ->route('admin.workflows.show', $copy)
            ->with('success', 'Workflow version created as draft.');
    }

    public function assignPrograms(AssignProgramsRequest $request, Workflow $workflow): RedirectResponse
    {
        $this->adminWorkflowService->assignPrograms(
            $workflow,
            $request->validated('program_ids') ?? [],
        );

        return redirect()
            ->back()
            ->with('success', 'Workflow programs updated.');
    }

    public function reassignTask(
        ReassignTaskRequest $request,
        Workflow $workflow,
        Assistance $assistance,
    ): RedirectResponse {
        ($this->reassignWorkflowTask)(
            $workflow,
            $assistance,
            $request->user(),
            $request->assignee(),
            $request->validated('remark'),
        );

        return redirect()
            ->back()
            ->with('success', 'Task reassigned.');
    }

    public function overrideTask(
        OverrideTaskRequest $request,
        Workflow $workflow,
        Assistance $assistance,
    ): RedirectResponse {
        ($this->reassignWorkflowTask)(
            $workflow,
            $assistance,
            $request->user(),
            $request->assignee(),
            $request->validated('remark'),
            true,
        );

        return redirect()
            ->back()
            ->with('success', 'Task assignment overridden.');
    }
}
