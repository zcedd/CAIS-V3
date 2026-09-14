<?php

namespace App\Services\User;

use App\Models\Department;
use App\Models\Program;
use App\Models\RequestStatus;
use App\Models\RequestSubStatus;
use App\Models\User;
use App\Models\Workflow;
use App\Models\WorkflowStep;
use App\Services\Workflow\EnsureDepartmentWorkflow;
use App\Services\Workflow\RequestStatusCatalog;
use App\Support\RequestStatusCode;
use App\Support\WorkflowTemplate;
use Illuminate\Support\Facades\DB;
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
        $workflow->loadMissing(['steps.requestStatus', 'steps.defaultSubStatus', 'steps.transitions.toRequestStatus']);

        return [
            'id' => $workflow->id,
            'name' => $workflow->name,
            'template' => $workflow->template instanceof WorkflowTemplate
                ? $workflow->template->value
                : $workflow->template,
            'is_default' => (bool) $workflow->is_default,
            'staff_entry_request_status_id' => $workflow->staff_entry_request_status_id,
            'public_entry_request_status_id' => $workflow->public_entry_request_status_id,
            'steps' => $workflow->steps->map(static fn (WorkflowStep $step): array => [
                'id' => $step->id,
                'request_status_id' => $step->request_status_id,
                'request_status_name' => $step->requestStatus?->name,
                'request_status_code' => $step->requestStatus?->code instanceof RequestStatusCode
                    ? $step->requestStatus->code->value
                    : $step->requestStatus?->code,
                'sort_order' => $step->sort_order,
                'default_request_sub_status_id' => $step->default_request_sub_status_id,
                'sla_hours' => $step->sla_hours,
                'requires_assignee' => (bool) $step->requires_assignee,
                'assigned_to_id' => $step->assigned_to_id !== null ? (int) $step->assigned_to_id : null,
                'permission' => $step->permission,
                'allows_skip_to_deliver' => (bool) $step->allows_skip_to_deliver,
                'transition_status_ids' => $step->allowedTargetStatusIds(),
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
            ->orderByDesc('is_default')
            ->orderBy('name')
            ->get(['id', 'name', 'is_default'])
            ->map(static fn (Workflow $workflow): array => [
                'id' => $workflow->id,
                'name' => $workflow->name.($workflow->is_default ? ' (department default)' : ''),
                'is_default' => (bool) $workflow->is_default,
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

            $workflow = Workflow::query()->create([
                'department_id' => $department->id,
                'name' => $validated['name'],
                'template' => WorkflowTemplate::Custom->value,
                'is_default' => (bool) ($validated['is_default'] ?? false),
                'staff_entry_request_status_id' => $validated['staff_entry_request_status_id'] ?? $submittedId,
                'public_entry_request_status_id' => $submittedId,
            ]);

            $this->syncSteps($workflow, $validated['steps']);

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
            $this->assertStepsNotRemovingOpenStatuses($workflow, $validated['steps']);

            if (($validated['is_default'] ?? false) === true && ! $workflow->is_default) {
                Workflow::query()
                    ->where('department_id', $workflow->department_id)
                    ->where('is_default', true)
                    ->whereKeyNot($workflow->id)
                    ->update(['is_default' => false]);
            }

            $workflow->update([
                'name' => $validated['name'],
                'template' => WorkflowTemplate::Custom->value,
                'is_default' => (bool) ($validated['is_default'] ?? $workflow->is_default),
                'staff_entry_request_status_id' => $validated['staff_entry_request_status_id']
                    ?? $workflow->staff_entry_request_status_id,
            ]);

            $this->syncSteps($workflow, $validated['steps']);

            return $workflow->fresh(['steps.transitions']) ?? $workflow;
        });
    }

    /**
     * @param  list<array{request_status_id: int, sort_order?: int, default_request_sub_status_id?: int|null, sla_hours?: int|null, requires_assignee?: bool, assigned_to_id?: int|null, allows_skip_to_deliver?: bool, transition_status_ids?: list<int>}>  $steps
     */
    private function syncSteps(Workflow $workflow, array $steps): void
    {
        $keptIds = [];

        foreach ($steps as $index => $stepPayload) {
            $step = WorkflowStep::query()->updateOrCreate(
                [
                    'workflow_id' => $workflow->id,
                    'request_status_id' => $stepPayload['request_status_id'],
                ],
                [
                    'sort_order' => $stepPayload['sort_order'] ?? (($index + 1) * 10),
                    'default_request_sub_status_id' => $stepPayload['default_request_sub_status_id'] ?? null,
                    'sla_hours' => $stepPayload['sla_hours'] ?? null,
                    'assigned_to_id' => $this->nullableInt($stepPayload['assigned_to_id'] ?? null),
                    'requires_assignee' => $this->nullableInt($stepPayload['assigned_to_id'] ?? null) !== null,
                    'permission' => $stepPayload['permission'] ?? null,
                    'allows_skip_to_deliver' => (bool) ($stepPayload['allows_skip_to_deliver'] ?? false),
                ],
            );

            $keptIds[] = $step->id;

            $targets = $stepPayload['transition_status_ids'] ?? [];
            $step->transitions()->whereNotIn('to_request_status_id', $targets)->delete();

            foreach ($targets as $targetId) {
                $step->transitions()->firstOrCreate([
                    'to_request_status_id' => (int) $targetId,
                ]);
            }
        }

        WorkflowStep::query()
            ->where('workflow_id', $workflow->id)
            ->whereNotIn('id', $keptIds)
            ->delete();
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
