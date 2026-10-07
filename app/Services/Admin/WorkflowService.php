<?php

namespace App\Services\Admin;

use App\Actions\Admin\AssignWorkflowPrograms;
use App\Actions\Admin\CreateWorkflowVersion;
use App\Actions\Admin\DuplicateWorkflow;
use App\Enums\PermissionName;
use App\Enums\WorkflowStatus;
use App\Models\Department;
use App\Models\Program;
use App\Models\User;
use App\Models\Workflow;
use App\Services\User\WorkflowService as StaffWorkflowService;
use App\Services\Workflow\PublishWorkflowValidator;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class WorkflowService
{
    private const DEFAULT_PER_PAGE = 15;

    /** @var list<string> */
    private const SORTABLE_COLUMNS = ['name', 'code', 'version', 'status', 'department'];

    public function __construct(
        private StaffWorkflowService $staffWorkflowService,
        private PublishWorkflowValidator $publishWorkflowValidator,
        private CreateWorkflowVersion $createWorkflowVersion,
        private DuplicateWorkflow $duplicateWorkflow,
        private AssignWorkflowPrograms $assignWorkflowPrograms,
    ) {}

    /**
     * @return list<array<string, mixed>>
     */
    public function listSummaries(?Department $department = null): array
    {
        return Workflow::query()
            ->with(['department:id,name,slug'])
            ->withCount(['programs', 'instances', 'assistances'])
            ->when(
                $department instanceof Department,
                fn ($query) => $query->where('department_id', $department->id),
            )
            ->orderBy('name')
            ->orderByDesc('version')
            ->orderByDesc('id')
            ->get()
            ->map(fn (Workflow $workflow): array => $this->summary($workflow))
            ->all();
    }

    /**
     * @param  list<string>  $statuses
     * @return LengthAwarePaginator<int, array<string, mixed>>
     */
    public function paginate(
        string $search,
        ?int $departmentId,
        array $statuses,
        string $sort,
        string $direction,
        int $perPage,
    ): LengthAwarePaginator {
        $sortColumn = in_array($sort, self::SORTABLE_COLUMNS, true) ? $sort : 'name';
        $sortDirection = $direction === 'asc' ? 'asc' : 'desc';

        $query = Workflow::query()
            ->with(['department:id,name,slug'])
            ->withCount(['programs', 'instances', 'assistances'])
            ->when($search !== '', function ($query) use ($search): void {
                $like = '%'.$search.'%';

                $query->where(function ($query) use ($like): void {
                    $query->where('name', 'like', $like)
                        ->orWhere('code', 'like', $like);
                });
            })
            ->when($departmentId !== null, fn ($query) => $query->where('department_id', $departmentId))
            ->when($statuses !== [], fn ($query) => $query->whereIn('status', $statuses));

        if ($sortColumn === 'department') {
            $query->orderBy(
                Department::query()
                    ->select('name')
                    ->whereColumn('departments.id', 'workflows.department_id')
                    ->limit(1),
                $sortDirection,
            );
        } else {
            $query->orderBy($sortColumn, $sortDirection);
        }

        return $query
            ->orderByDesc('id')
            ->paginate($perPage > 0 ? $perPage : self::DEFAULT_PER_PAGE)
            ->withQueryString()
            ->through(fn (Workflow $workflow): array => $this->summary($workflow));
    }

    /**
     * @return array<string, mixed>
     */
    public function summary(Workflow $workflow): array
    {
        $status = $workflow->status instanceof WorkflowStatus
            ? $workflow->status
            : WorkflowStatus::tryFrom((string) $workflow->status) ?? WorkflowStatus::Draft;

        return [
            'id' => $workflow->id,
            'name' => $workflow->name,
            'code' => $workflow->code,
            'description' => $workflow->description,
            'version' => (int) $workflow->version,
            'status' => $status->value,
            'status_label' => $status->label(),
            'is_mutable' => $status->isMutable(),
            'is_default' => (bool) $workflow->is_default,
            'is_referenced' => $workflow->isReferenced(),
            'department' => $workflow->department?->only(['id', 'name', 'slug']),
        ];
    }

    /**
     * @param  array<string, mixed>  $validated
     */
    public function create(Department $department, array $validated): Workflow
    {
        return $this->staffWorkflowService->create($department, [
            ...$validated,
            'steps' => $validated['steps'] ?? [],
        ]);
    }

    /**
     * @param  array<string, mixed>  $validated
     */
    public function update(Workflow $workflow, array $validated): Workflow
    {
        return $this->staffWorkflowService->update($workflow, $validated);
    }

    public function delete(Workflow $workflow): string
    {
        if ($workflow->isReferenced()) {
            $this->deactivate($workflow);

            return 'deactivated';
        }

        $workflow->delete();

        return 'deleted';
    }

    public function publish(Workflow $workflow): Workflow
    {
        if ($workflow->status !== WorkflowStatus::Draft) {
            throw ValidationException::withMessages([
                'workflow' => 'Only draft workflows can be published.',
            ]);
        }

        $this->assertPublishable($workflow);

        $workflow->forceFill([
            'status' => WorkflowStatus::Published,
        ])->save();

        return $workflow->refresh();
    }

    public function activate(Workflow $workflow): Workflow
    {
        if (! in_array($workflow->status, [WorkflowStatus::Published, WorkflowStatus::Inactive], true)) {
            throw ValidationException::withMessages([
                'workflow' => 'Publish the workflow before activating it.',
            ]);
        }

        $this->assertPublishable($workflow);

        return DB::transaction(function () use ($workflow): Workflow {
            Workflow::query()
                ->where('department_id', $workflow->department_id)
                ->where('code', $workflow->code)
                ->whereKeyNot($workflow->id)
                ->where('status', WorkflowStatus::Active)
                ->update(['status' => WorkflowStatus::Inactive]);

            $workflow->forceFill([
                'status' => WorkflowStatus::Active,
            ])->save();

            return $workflow->refresh();
        });
    }

    public function deactivate(Workflow $workflow): Workflow
    {
        $workflow->forceFill([
            'status' => WorkflowStatus::Inactive,
        ])->save();

        return $workflow->refresh();
    }

    public function version(Workflow $workflow): Workflow
    {
        return ($this->createWorkflowVersion)($workflow);
    }

    public function duplicate(Workflow $workflow, string $name, string $code): Workflow
    {
        return ($this->duplicateWorkflow)($workflow, $name, $code);
    }

    /**
     * @param  list<int>  $programIds
     */
    public function assignPrograms(Workflow $workflow, array $programIds): Workflow
    {
        if ($programIds !== [] && $workflow->status !== WorkflowStatus::Active) {
            throw ValidationException::withMessages([
                'program_ids' => 'Only active workflows can be assigned to programs.',
            ]);
        }

        return ($this->assignWorkflowPrograms)($workflow, $programIds);
    }

    /**
     * @return list<array{id: int, name: string, workflow_id: int|null}>
     */
    public function departmentPrograms(Department $department): array
    {
        return Program::query()
            ->where('department_id', $department->id)
            ->orderBy('name')
            ->get(['id', 'name', 'workflow_id'])
            ->map(static fn (Program $program): array => [
                'id' => $program->id,
                'name' => $program->name,
                'workflow_id' => $program->workflow_id !== null ? (int) $program->workflow_id : null,
            ])
            ->all();
    }

    /**
     * @return array{
     *     create: bool,
     *     update: bool,
     *     publish: bool,
     *     manage: bool,
     *     version: bool,
     *     assign: bool,
     *     delete: bool,
     *     duplicate: bool,
     *     reassign: bool,
     *     override: bool
     * }
     */
    public function abilities(?User $user, ?Workflow $workflow = null, ?Department $department = null): array
    {
        if (! $user instanceof User) {
            return [
                'create' => false,
                'update' => false,
                'publish' => false,
                'manage' => false,
                'version' => false,
                'assign' => false,
                'delete' => false,
                'duplicate' => false,
                'reassign' => false,
                'override' => false,
            ];
        }

        return [
            'create' => $department instanceof Department
                ? $user->can('create', [Workflow::class, $department])
                : $user->can(PermissionName::WorkflowCreate->value),
            'update' => $workflow instanceof Workflow
                ? $user->can('update', $workflow)
                : $user->can(PermissionName::WorkflowUpdate->value),
            'publish' => $workflow instanceof Workflow
                ? $user->can('publish', $workflow)
                : $user->can(PermissionName::WorkflowPublish->value),
            'manage' => $workflow instanceof Workflow
                ? $user->can('manage', $workflow)
                : $user->can(PermissionName::WorkflowManage->value),
            'version' => $workflow instanceof Workflow
                ? $user->can('version', $workflow)
                : $user->can(PermissionName::WorkflowVersion->value),
            'assign' => $workflow instanceof Workflow
                ? $user->can('assign', $workflow)
                : $user->can(PermissionName::WorkflowAssign->value),
            'delete' => $workflow instanceof Workflow
                ? $user->can('delete', $workflow)
                : $user->can(PermissionName::WorkflowDelete->value),
            'duplicate' => $department instanceof Department
                ? $user->can('create', [Workflow::class, $department])
                : $user->can(PermissionName::WorkflowCreate->value),
            'reassign' => $workflow instanceof Workflow
                ? $user->can('reassignTask', $workflow)
                : $user->can(PermissionName::WorkflowTaskReassign->value),
            'override' => $workflow instanceof Workflow
                ? $user->can('overrideTask', $workflow)
                : $user->can(PermissionName::WorkflowTaskOverride->value),
        ];
    }

    private function assertPublishable(Workflow $workflow): void
    {
        $errors = $this->publishWorkflowValidator->errors($workflow);

        if ($errors === []) {
            return;
        }

        $messages = [];

        foreach (array_values($errors) as $index => $error) {
            $messages['workflow.'.$index] = $error;
        }

        throw ValidationException::withMessages($messages);
    }
}
