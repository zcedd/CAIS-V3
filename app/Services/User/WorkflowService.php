<?php

namespace App\Services\User;

use App\Enums\RequestStatusCode;
use App\Enums\WorkflowAssignmentType;
use App\Enums\WorkflowStatus;
use App\Enums\WorkflowStepType;
use App\Enums\WorkflowTemplate;
use App\Enums\WorkflowTransitionAction;
use App\Models\Department;
use App\Models\Program;
use App\Models\RequestStatus;
use App\Models\RequestSubStatus;
use App\Models\User;
use App\Models\Workflow;
use App\Models\WorkflowStep;
use App\Services\Workflow\EnsureDepartmentWorkflow;
use App\Services\Workflow\RequestStatusCatalog;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class WorkflowService
{
    public function __construct(
        private EnsureDepartmentWorkflow $ensureDepartmentWorkflow,
        private RequestStatusCatalog $catalog,
    ) {}

    /**
     * @return list<array<string, mixed>>
     */
    public function listForDepartment(Department $department): array
    {
        $this->ensureDepartmentWorkflow->defaultFor($department);

        return Workflow::query()
            ->where('department_id', $department->id)
            ->with(['steps.requestStatus', 'steps.defaultSubStatus', 'steps.transitions.toRequestStatus'])
            ->orderByDesc('is_default')
            ->orderBy('name')
            ->get()
            ->map(fn (Workflow $workflow): array => $this->serialize($workflow))
            ->all();
    }

    /**
     * @return array<string, mixed>
     */
    public function serialize(Workflow $workflow): array
    {
        $workflow->loadMissing([
            'steps.requestStatus',
            'steps.defaultSubStatus',
            'steps.transitions.toRequestStatus',
            'steps.transitions.toStep',
            'programs:id,name',
        ]);

        $status = $workflow->status instanceof WorkflowStatus
            ? $workflow->status
            : WorkflowStatus::tryFrom((string) $workflow->status) ?? WorkflowStatus::Active;

        return [
            'id' => $workflow->id,
            'name' => $workflow->name,
            'code' => $workflow->code,
            'description' => $workflow->description,
            'version' => (int) $workflow->version,
            'status' => $status->value,
            'status_label' => $status->label(),
            'is_mutable' => $status->isMutable(),
            'template' => $workflow->template instanceof WorkflowTemplate
                ? $workflow->template->value
                : $workflow->template,
            'is_default' => (bool) $workflow->is_default,
            'staff_entry_request_status_id' => $workflow->staff_entry_request_status_id,
            'public_entry_request_status_id' => $workflow->public_entry_request_status_id,
            'programs' => $workflow->programs
                ->map(static fn (Program $program): array => [
                    'id' => $program->id,
                    'name' => $program->name,
                ])
                ->values()
                ->all(),
            'steps' => $workflow->steps->map(static fn (WorkflowStep $step): array => [
                'id' => $step->id,
                'code' => $step->code,
                'name' => $step->displayName(),
                'step_type' => $step->step_type instanceof WorkflowStepType
                    ? $step->step_type->value
                    : $step->step_type,
                'request_status_id' => $step->request_status_id,
                'request_status_name' => $step->requestStatus?->name,
                'request_status_code' => $step->requestStatus?->code instanceof RequestStatusCode
                    ? $step->requestStatus->code->value
                    : $step->requestStatus?->code,
                'sort_order' => $step->sort_order,
                'is_start' => (bool) $step->is_start,
                'is_end' => (bool) $step->is_end,
                'default_request_sub_status_id' => $step->default_request_sub_status_id,
                'sla_hours' => $step->sla_hours,
                'requires_assignee' => (bool) $step->requires_assignee,
                'assignment_type' => $step->assignment_type instanceof WorkflowAssignmentType
                    ? $step->assignment_type->value
                    : $step->assignment_type,
                'assigned_role' => $step->assigned_role,
                'assigned_department_id' => $step->assigned_department_id,
                'assigned_to_id' => $step->assigned_to_id !== null ? (int) $step->assigned_to_id : null,
                'automatic_assignment' => (bool) $step->automatic_assignment,
                'permission' => $step->permission,
                'allows_skip_to_deliver' => (bool) $step->allows_skip_to_deliver,
                'transition_status_ids' => $step->allowedTargetStatusIds(),
                'transitions' => $step->transitions->map(static fn ($transition): array => [
                    'id' => $transition->id,
                    'to_step_id' => $transition->to_step_id,
                    'to_request_status_id' => $transition->to_request_status_id,
                    'action' => $transition->action instanceof WorkflowTransitionAction
                        ? $transition->action->value
                        : $transition->action,
                    'label' => $transition->displayLabel(),
                    'requires_comment' => (bool) $transition->requires_comment,
                    'conditions' => $transition->conditions,
                ])->values()->all(),
            ])->values()->all(),
        ];
    }

    /**
     * @return list<array{id: int, name: string, is_default: bool}>
     */
    public function optionsForDepartment(Department $department): array
    {
        $this->ensureDepartmentWorkflow->defaultFor($department);

        return Workflow::query()
            ->where('department_id', $department->id)
            ->where('status', WorkflowStatus::Active)
            ->orderByDesc('is_default')
            ->orderBy('name')
            ->orderByDesc('version')
            ->get(['id', 'name', 'is_default', 'code', 'version', 'status'])
            ->map(static fn (Workflow $workflow): array => [
                'id' => $workflow->id,
                'name' => sprintf(
                    '%s v%d%s',
                    $workflow->name,
                    (int) $workflow->version,
                    $workflow->is_default ? ' (department default)' : '',
                ),
                'is_default' => (bool) $workflow->is_default,
                'version' => (int) $workflow->version,
                'status' => $workflow->status instanceof WorkflowStatus
                    ? $workflow->status->value
                    : (string) $workflow->status,
            ])
            ->all();
    }

    /**
     * @return list<array{id: int, name: string, code: string|null}>
     */
    public function activeStatuses(): array
    {
        $this->catalog->ensure();

        return RequestStatus::query()
            ->active()
            ->orderBy('sort_order')
            ->get(['id', 'name', 'code'])
            ->map(static fn (RequestStatus $status): array => [
                'id' => $status->id,
                'name' => $status->name,
                'code' => $status->code instanceof RequestStatusCode ? $status->code->value : $status->code,
            ])
            ->all();
    }

    /**
     * @return list<array{id: int, name: string, request_status_id: int}>
     */
    public function activeReasons(): array
    {
        $this->catalog->ensure();

        return RequestSubStatus::query()
            ->active()
            ->orderBy('name')
            ->get(['id', 'name', 'request_status_id'])
            ->map(static fn (RequestSubStatus $reason): array => [
                'id' => $reason->id,
                'name' => $reason->name,
                'request_status_id' => $reason->request_status_id,
            ])
            ->all();
    }

    /**
     * @param  array{
     *     name: string,
     *     template?: string,
     *     is_default?: bool,
     *     staff_entry_request_status_id?: int,
     *     steps: list<array{
     *         request_status_id: int,
     *         sort_order?: int,
     *         default_request_sub_status_id?: int|null,
     *         sla_hours?: int|null,
     *         requires_assignee?: bool,
     *         allows_skip_to_deliver?: bool,
     *         transition_status_ids?: list<int>
     *     }>
     * }  $validated
     */
    public function create(Department $department, array $validated): Workflow
    {
        return DB::transaction(function () use ($department, $validated): Workflow {
            $template = WorkflowTemplate::tryFrom($validated['template'] ?? WorkflowTemplate::Custom->value)
                ?? WorkflowTemplate::Custom;

            if ($template !== WorkflowTemplate::Custom && ($validated['steps'] ?? []) === []) {
                $isDefault = (bool) ($validated['is_default'] ?? false);

                return $this->ensureDepartmentWorkflow->create(
                    $department,
                    $template,
                    $isDefault,
                    $validated['name'],
                );
            }

            if (($validated['is_default'] ?? false) === true) {
                Workflow::query()
                    ->where('department_id', $department->id)
                    ->where('is_default', true)
                    ->update(['is_default' => false]);
            }

            $submittedId = $this->catalog->parentId(RequestStatusCode::Submitted);

            $version = (int) ($validated['version'] ?? 1);

            $workflow = Workflow::query()->create([
                'department_id' => $department->id,
                'name' => $validated['name'],
                'code' => $this->uniqueCode(
                    $department,
                    $validated['code'] ?? $validated['name'],
                    null,
                    $version,
                ),
                'description' => $validated['description'] ?? null,
                'version' => $version > 0 ? $version : 1,
                'status' => WorkflowStatus::Draft,
                'template' => WorkflowTemplate::Custom->value,
                'is_default' => (bool) ($validated['is_default'] ?? false),
                'staff_entry_request_status_id' => $validated['staff_entry_request_status_id'] ?? $submittedId,
                'public_entry_request_status_id' => $submittedId,
            ]);

            $this->syncConfiguredSteps($workflow, $validated['steps']);

            return $workflow->fresh(['steps.transitions']) ?? $workflow;
        });
    }

    /**
     * @param  array{
     *     name: string,
     *     is_default?: bool,
     *     staff_entry_request_status_id?: int,
     *     steps: list<array{
     *         id?: int,
     *         request_status_id: int,
     *         sort_order?: int,
     *         default_request_sub_status_id?: int|null,
     *         sla_hours?: int|null,
     *         requires_assignee?: bool,
     *         allows_skip_to_deliver?: bool,
     *         transition_status_ids?: list<int>
     *     }>
     * }  $validated
     */
    public function update(Workflow $workflow, array $validated): Workflow
    {
        return DB::transaction(function () use ($workflow, $validated): Workflow {
            $workflow->loadMissing(['department', 'steps']);

            if (! $workflow->isMutable()) {
                throw ValidationException::withMessages([
                    'status' => 'Published and active workflows cannot be edited. Create a new version instead.',
                ]);
            }

            $this->assertStepsNotRemovingOpenStatuses($workflow, $validated['steps']);

            if (($validated['is_default'] ?? false) === true && ! $workflow->is_default) {
                Workflow::query()
                    ->where('department_id', $workflow->department_id)
                    ->where('is_default', true)
                    ->whereKeyNot($workflow->id)
                    ->update(['is_default' => false]);
            }

            $payload = [
                'name' => $validated['name'],
                'template' => WorkflowTemplate::Custom->value,
                'is_default' => (bool) ($validated['is_default'] ?? $workflow->is_default),
                'staff_entry_request_status_id' => $validated['staff_entry_request_status_id']
                    ?? $workflow->staff_entry_request_status_id,
            ];

            if (array_key_exists('code', $validated) && is_string($validated['code']) && $validated['code'] !== '') {
                $payload['code'] = $this->uniqueCode(
                    $workflow->department ?? Department::query()->findOrFail($workflow->department_id),
                    $validated['code'],
                    $workflow->id,
                    (int) $workflow->version,
                );
            }

            if (array_key_exists('description', $validated)) {
                $payload['description'] = $validated['description'];
            }

            $workflow->update($payload);

            $this->syncConfiguredSteps($workflow, $validated['steps']);

            return $workflow->fresh(['steps.transitions']) ?? $workflow;
        });
    }

    /**
     * @param  list<array<string, mixed>>  $steps
     */
    public function syncConfiguredSteps(Workflow $workflow, array $steps): void
    {
        $keptIds = [];
        $usedCodes = [];

        foreach ($steps as $index => $stepPayload) {
            $assignmentType = WorkflowAssignmentType::tryFrom((string) ($stepPayload['assignment_type'] ?? ''))
                ?? WorkflowAssignmentType::None;
            $assignedToId = $this->nullableInt($stepPayload['assigned_to_id'] ?? null);

            if ($assignmentType === WorkflowAssignmentType::None && $assignedToId !== null) {
                $assignmentType = WorkflowAssignmentType::User;
            }

            $code = $this->uniqueStepCode(
                (string) ($stepPayload['code'] ?? ''),
                (int) $stepPayload['request_status_id'],
                $usedCodes,
            );
            $usedCodes[] = $code;

            $step = isset($stepPayload['id'])
                ? WorkflowStep::query()
                    ->where('workflow_id', $workflow->id)
                    ->whereKey($stepPayload['id'])
                    ->first()
                : null;

            $step ??= new WorkflowStep;

            $step->fill([
                'workflow_id' => $workflow->id,
                'code' => $code,
                'name' => $stepPayload['name'] ?? $step->name,
                'step_type' => $stepPayload['step_type'] ?? $step->step_type ?? WorkflowStepType::Custom,
                'request_status_id' => $stepPayload['request_status_id'],
                'sort_order' => $stepPayload['sort_order'] ?? (($index + 1) * 10),
                'is_start' => (bool) ($stepPayload['is_start'] ?? $index === 0),
                'is_end' => (bool) ($stepPayload['is_end'] ?? false),
                'default_request_sub_status_id' => $stepPayload['default_request_sub_status_id'] ?? null,
                'sla_hours' => $stepPayload['sla_hours'] ?? null,
                'assigned_to_id' => $assignedToId,
                'assigned_role' => $stepPayload['assigned_role'] ?? null,
                'assigned_department_id' => $this->nullableInt($stepPayload['assigned_department_id'] ?? null),
                'assignment_type' => $assignmentType,
                'automatic_assignment' => (bool) ($stepPayload['automatic_assignment'] ?? $assignedToId !== null),
                'requires_assignee' => $assignmentType !== WorkflowAssignmentType::None || $assignedToId !== null,
                'permission' => $stepPayload['permission'] ?? null,
                'allows_skip_to_deliver' => (bool) ($stepPayload['allows_skip_to_deliver'] ?? false),
            ])->save();

            $keptIds[] = $step->id;
        }

        WorkflowStep::query()
            ->where('workflow_id', $workflow->id)
            ->whereNotIn('id', $keptIds)
            ->delete();

        $stepsByStatus = WorkflowStep::query()
            ->where('workflow_id', $workflow->id)
            ->with('requestStatus')
            ->get()
            ->keyBy(static fn (WorkflowStep $step): int => (int) $step->request_status_id);

        foreach ($steps as $index => $stepPayload) {
            $step = WorkflowStep::query()->find($keptIds[$index] ?? 0);

            if (! $step instanceof WorkflowStep) {
                continue;
            }

            $targets = $stepPayload['transition_status_ids'] ?? [];
            $keptTransitionIds = [];

            foreach ($targets as $targetId) {
                $targetStatusId = (int) $targetId;
                $toStep = $stepsByStatus->get($targetStatusId);
                $action = WorkflowTransitionAction::Advance;
                $toStatus = $toStep?->requestStatus?->code;

                if ($toStatus === RequestStatusCode::Denied) {
                    $action = WorkflowTransitionAction::Reject;
                } elseif ($toStatus === RequestStatusCode::OnHold) {
                    $action = WorkflowTransitionAction::Hold;
                } elseif ($toStatus === RequestStatusCode::Closed) {
                    $action = WorkflowTransitionAction::Close;
                }

                $transition = $step->transitions()->updateOrCreate(
                    ['to_request_status_id' => $targetStatusId],
                    [
                        'to_step_id' => $toStep?->id,
                        'action' => $action,
                        'requires_comment' => $action === WorkflowTransitionAction::Reject,
                    ],
                );
                $keptTransitionIds[] = $transition->id;
            }

            $step->transitions()
                ->when(
                    $keptTransitionIds !== [],
                    fn ($query) => $query->whereNotIn('id', $keptTransitionIds),
                    fn ($query) => $query,
                )
                ->delete();
        }
    }

    /**
     * @param  list<string>  $usedCodes
     */
    private function uniqueStepCode(string $requested, int $requestStatusId, array $usedCodes): string
    {
        $source = $requested;

        if ($source === '') {
            $fallback = RequestStatus::query()->whereKey($requestStatusId)->value('code');
            $source = $fallback instanceof RequestStatusCode ? $fallback->value : '';
        }

        $base = Str::upper(Str::slug($source, '_'));
        $base = $base !== '' ? $base : 'STEP';
        $code = $base;
        $suffix = 2;

        while (in_array($code, $usedCodes, true)) {
            $code = $base.'_'.$suffix;
            $suffix++;
        }

        return $code;
    }

    public function uniqueCode(Department $department, string $name, ?int $ignoreId = null, ?int $version = null): string
    {
        $base = Str::upper(Str::slug($name, '_'));
        $base = $base !== '' ? $base : 'WORKFLOW';
        $code = $base;
        $suffix = 2;

        while (Workflow::query()
            ->where('department_id', $department->id)
            ->where('code', $code)
            ->when($version !== null, fn ($query) => $query->where('version', $version))
            ->when($ignoreId !== null, fn ($query) => $query->whereKeyNot($ignoreId))
            ->exists()) {
            $code = $base.'_'.$suffix;
            $suffix++;
        }

        return $code;
    }

    /**
     * @param  list<array{request_status_id: int}>  $steps
     */
    private function assertStepsNotRemovingOpenStatuses(Workflow $workflow, array $steps): void
    {
        $nextStatusIds = collect($steps)->pluck('request_status_id')->map(static fn ($id): int => (int) $id)->all();
        $removedIds = $workflow->steps
            ->pluck('request_status_id')
            ->map(static fn ($id): int => (int) $id)
            ->diff($nextStatusIds)
            ->all();

        if ($removedIds === []) {
            return;
        }

        $inUse = Program::query()
            ->where(function ($query) use ($workflow): void {
                $query->where('workflow_id', $workflow->id);

                if ($workflow->is_default) {
                    $query->orWhere(function ($defaultQuery) use ($workflow): void {
                        $defaultQuery
                            ->where('department_id', $workflow->department_id)
                            ->whereNull('workflow_id');
                    });
                }
            })
            ->whereHas('assistances', function ($assistanceQuery) use ($removedIds): void {
                $assistanceQuery
                    ->open()
                    ->whereHas('currentRequestSubStatus', function ($statusQuery) use ($removedIds): void {
                        $statusQuery->whereIn('request_status_id', $removedIds);
                    });
            })
            ->exists();

        if ($inUse) {
            throw ValidationException::withMessages([
                'steps' => 'Cannot remove a stage that open assistance requests currently sit on.',
            ]);
        }
    }

    /**
     * @return list<array{id: int, name: string}>
     */
    public function departmentStaffForSelect(Department $department): array
    {
        return User::query()
            ->where('department_id', $department->id)
            ->orderBy('lastName')
            ->orderBy('firstName')
            ->get(['id', 'firstName', 'lastName'])
            ->map(static fn (User $user): array => [
                'id' => $user->id,
                'name' => trim($user->firstName.' '.$user->lastName),
            ])
            ->all();
    }

    private function nullableInt(mixed $value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }

        $id = (int) $value;

        return $id > 0 ? $id : null;
    }
}
