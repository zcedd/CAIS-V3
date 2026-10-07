<?php

namespace App\Services\Workflow;

use App\Enums\RequestStatusCode;
use App\Enums\RequestSubStatusCode;
use App\Enums\RoleName;
use App\Enums\WorkflowAssignmentType;
use App\Enums\WorkflowStatus;
use App\Enums\WorkflowStepType;
use App\Enums\WorkflowTemplate;
use App\Enums\WorkflowTransitionAction;
use App\Models\Department;
use App\Models\Workflow;
use App\Models\WorkflowStep;
use App\Models\WorkflowStepTransition;
use Illuminate\Support\Str;

class EnsureDepartmentWorkflow
{
    public function __construct(
        private RequestStatusCatalog $catalog,
    ) {}

    public function defaultFor(Department $department): Workflow
    {
        $this->catalog->ensure();

        $existing = Workflow::query()
            ->where('department_id', $department->id)
            ->where('is_default', true)
            ->first();

        if ($existing instanceof Workflow) {
            return $existing;
        }

        return $this->create($department, WorkflowTemplate::Standard, true);
    }

    public function create(
        Department $department,
        WorkflowTemplate $template,
        bool $isDefault = false,
        ?string $name = null,
    ): Workflow {
        $this->catalog->ensure();

        if ($isDefault) {
            Workflow::query()
                ->where('department_id', $department->id)
                ->where('is_default', true)
                ->update(['is_default' => false]);
        }

        $submittedId = $this->catalog->parentId(RequestStatusCode::Submitted);

        $workflow = Workflow::query()->create([
            'department_id' => $department->id,
            'name' => $name ?? ($template === WorkflowTemplate::Standard
                ? 'Standard Assistance Workflow'
                : $template->label()),
            'code' => $this->uniqueCode($department, $name ?? $template->label()),
            'version' => 1,
            'status' => WorkflowStatus::Active,
            'template' => $template->value,
            'is_default' => $isDefault,
            'staff_entry_request_status_id' => $submittedId,
            'public_entry_request_status_id' => $submittedId,
        ]);

        $this->syncTemplateSteps($workflow, $template);

        return $workflow->fresh(['steps.transitions']) ?? $workflow;
    }

    public function syncTemplateSteps(Workflow $workflow, WorkflowTemplate $template): void
    {
        $definitions = $template === WorkflowTemplate::Standard
            ? $this->standardSteps()
            : $this->legacyHappyPathSteps($template);

        $stepIdsByCode = [];

        foreach ($definitions as $index => $definition) {
            $step = WorkflowStep::query()->updateOrCreate(
                [
                    'workflow_id' => $workflow->id,
                    'code' => $definition['code'],
                ],
                [
                    'name' => $definition['name'],
                    'step_type' => $definition['step_type'],
                    'request_status_id' => $this->catalog->parentId($definition['status']),
                    'sort_order' => ($index + 1) * 10,
                    'is_start' => $definition['is_start'],
                    'is_end' => $definition['is_end'],
                    'default_request_sub_status_id' => $this->catalog->reasonId($definition['reason']),
                    'sla_hours' => $definition['sla_hours'],
                    'requires_assignee' => $definition['assignment_type'] !== WorkflowAssignmentType::None,
                    'assignment_type' => $definition['assignment_type'],
                    'assigned_role' => $definition['assigned_role'],
                    'automatic_assignment' => false,
                    'permission' => null,
                    'allows_skip_to_deliver' => $definition['allows_skip_to_deliver'],
                ],
            );

            $stepIdsByCode[$definition['code']] = $step->id;
        }

        WorkflowStep::query()
            ->where('workflow_id', $workflow->id)
            ->whereNotIn('id', array_values($stepIdsByCode))
            ->delete();

        foreach ($definitions as $definition) {
            $this->syncNamedTransitions(
                $stepIdsByCode[$definition['code']],
                $stepIdsByCode,
                $definition['targets'],
            );
        }
    }

    /**
     * @return list<array{
     *     code: string,
     *     name: string,
     *     step_type: WorkflowStepType,
     *     status: RequestStatusCode,
     *     reason: RequestSubStatusCode,
     *     sla_hours: int|null,
     *     is_start: bool,
     *     is_end: bool,
     *     assignment_type: WorkflowAssignmentType,
     *     assigned_role: string|null,
     *     allows_skip_to_deliver: bool,
     *     targets: list<string>
     * }>
     */
    private function standardSteps(): array
    {
        $role = RoleName::Assistance->value;

        return [
            [
                'code' => 'SUBMITTED',
                'name' => 'Submitted',
                'step_type' => WorkflowStepType::Start,
                'status' => RequestStatusCode::Submitted,
                'reason' => RequestSubStatusCode::AwaitingReview,
                'sla_hours' => 48,
                'is_start' => true,
                'is_end' => false,
                'assignment_type' => WorkflowAssignmentType::Role,
                'assigned_role' => $role,
                'allows_skip_to_deliver' => false,
                'targets' => ['VERIFY_BENEFICIARY', 'ON_HOLD', 'DENIED', 'COMPLETED'],
            ],
            [
                'code' => 'VERIFY_BENEFICIARY',
                'name' => 'Verify beneficiary',
                'step_type' => WorkflowStepType::Verification,
                'status' => RequestStatusCode::Review,
                'reason' => RequestSubStatusCode::UnderReview,
                'sla_hours' => 72,
                'is_start' => false,
                'is_end' => false,
                'assignment_type' => WorkflowAssignmentType::Role,
                'assigned_role' => $role,
                'allows_skip_to_deliver' => false,
                'targets' => ['EVALUATE', 'APPROVE', 'ON_HOLD', 'DENIED', 'COMPLETED'],
            ],
            [
                'code' => 'EVALUATE',
                'name' => 'Evaluate',
                'step_type' => WorkflowStepType::Evaluation,
                'status' => RequestStatusCode::Review,
                'reason' => RequestSubStatusCode::UnderReview,
                'sla_hours' => 72,
                'is_start' => false,
                'is_end' => false,
                'assignment_type' => WorkflowAssignmentType::Role,
                'assigned_role' => $role,
                'allows_skip_to_deliver' => false,
                'targets' => ['APPROVE', 'ON_HOLD', 'DENIED', 'COMPLETED'],
            ],
            [
                'code' => 'APPROVE',
                'name' => 'Approve',
                'step_type' => WorkflowStepType::Approval,
                'status' => RequestStatusCode::Approved,
                'reason' => RequestSubStatusCode::Approved,
                'sla_hours' => 48,
                'is_start' => false,
                'is_end' => false,
                'assignment_type' => WorkflowAssignmentType::Role,
                'assigned_role' => $role,
                'allows_skip_to_deliver' => false,
                'targets' => ['PREPARE', 'RELEASE', 'ON_HOLD', 'DENIED', 'COMPLETED'],
            ],
            [
                'code' => 'PREPARE',
                'name' => 'Prepare',
                'step_type' => WorkflowStepType::Preparation,
                'status' => RequestStatusCode::Approved,
                'reason' => RequestSubStatusCode::Approved,
                'sla_hours' => 48,
                'is_start' => false,
                'is_end' => false,
                'assignment_type' => WorkflowAssignmentType::Role,
                'assigned_role' => $role,
                'allows_skip_to_deliver' => false,
                'targets' => ['RELEASE', 'ON_HOLD', 'DENIED', 'COMPLETED'],
            ],
            [
                'code' => 'RELEASE',
                'name' => 'Release',
                'step_type' => WorkflowStepType::Release,
                'status' => RequestStatusCode::Delivered,
                'reason' => RequestSubStatusCode::Delivered,
                'sla_hours' => null,
                'is_start' => false,
                'is_end' => false,
                'assignment_type' => WorkflowAssignmentType::Role,
                'assigned_role' => $role,
                'allows_skip_to_deliver' => false,
                'targets' => ['COMPLETED', 'DENIED'],
            ],
            [
                'code' => 'COMPLETED',
                'name' => 'Completed',
                'step_type' => WorkflowStepType::Completion,
                'status' => RequestStatusCode::Closed,
                'reason' => RequestSubStatusCode::Closed,
                'sla_hours' => null,
                'is_start' => false,
                'is_end' => true,
                'assignment_type' => WorkflowAssignmentType::None,
                'assigned_role' => null,
                'allows_skip_to_deliver' => false,
                'targets' => [],
            ],
            [
                'code' => 'ON_HOLD',
                'name' => 'On Hold',
                'step_type' => WorkflowStepType::Hold,
                'status' => RequestStatusCode::OnHold,
                'reason' => RequestSubStatusCode::AwaitingInformation,
                'sla_hours' => null,
                'is_start' => false,
                'is_end' => false,
                'assignment_type' => WorkflowAssignmentType::None,
                'assigned_role' => null,
                'allows_skip_to_deliver' => false,
                'targets' => ['VERIFY_BENEFICIARY', 'DENIED', 'COMPLETED'],
            ],
            [
                'code' => 'DENIED',
                'name' => 'Denied',
                'step_type' => WorkflowStepType::Rejection,
                'status' => RequestStatusCode::Denied,
                'reason' => RequestSubStatusCode::Denied,
                'sla_hours' => null,
                'is_start' => false,
                'is_end' => false,
                'assignment_type' => WorkflowAssignmentType::None,
                'assigned_role' => null,
                'allows_skip_to_deliver' => false,
                'targets' => ['COMPLETED'],
            ],
        ];
    }

    /**
     * @return list<array{
     *     code: string,
     *     name: string,
     *     step_type: WorkflowStepType,
     *     status: RequestStatusCode,
     *     reason: RequestSubStatusCode,
     *     sla_hours: int|null,
     *     is_start: bool,
     *     is_end: bool,
     *     assignment_type: WorkflowAssignmentType,
     *     assigned_role: string|null,
     *     allows_skip_to_deliver: bool,
     *     targets: list<string>
     * }>
     */
    private function legacyHappyPathSteps(WorkflowTemplate $template): array
    {
        $happyPath = $template->happyPath();
        $reasons = $this->defaultReasons();
        $steps = [];

        foreach ($happyPath as $index => $stage) {
            $next = $happyPath[$index + 1] ?? null;
            $targets = [];

            if ($next instanceof RequestStatusCode) {
                $targets[] = Str::upper($next->value);
            }

            if (! $stage->isTerminal() || $stage === RequestStatusCode::Delivered) {
                if ($stage !== RequestStatusCode::Closed) {
                    $targets[] = 'ON_HOLD';
                    $targets[] = 'DENIED';
                    $targets[] = 'CLOSED';
                }
            }

            if ($template === WorkflowTemplate::WalkIn && $stage === RequestStatusCode::Submitted) {
                $targets[] = 'DELIVERED';
            }

            $steps[] = [
                'code' => Str::upper($stage->value),
                'name' => $stage->label(),
                'step_type' => WorkflowStepType::fromStatusCode(
                    $stage,
                    $index === 0,
                    $stage === RequestStatusCode::Closed,
                ),
                'status' => $stage,
                'reason' => $reasons[$stage->value],
                'sla_hours' => $this->defaultSlaHours($stage),
                'is_start' => $index === 0,
                'is_end' => $stage === RequestStatusCode::Closed,
                'assignment_type' => WorkflowAssignmentType::None,
                'assigned_role' => null,
                'allows_skip_to_deliver' => $template === WorkflowTemplate::WalkIn
                    && $stage === RequestStatusCode::Submitted,
                'targets' => array_values(array_unique($targets)),
            ];
        }

        $resumeCode = Str::upper($happyPath[0]->value);

        $steps[] = [
            'code' => 'ON_HOLD',
            'name' => 'On Hold',
            'step_type' => WorkflowStepType::Hold,
            'status' => RequestStatusCode::OnHold,
            'reason' => RequestSubStatusCode::AwaitingInformation,
            'sla_hours' => null,
            'is_start' => false,
            'is_end' => false,
            'assignment_type' => WorkflowAssignmentType::None,
            'assigned_role' => null,
            'allows_skip_to_deliver' => false,
            'targets' => [$resumeCode, 'DENIED', 'CLOSED'],
        ];

        $steps[] = [
            'code' => 'DENIED',
            'name' => 'Denied',
            'step_type' => WorkflowStepType::Rejection,
            'status' => RequestStatusCode::Denied,
            'reason' => RequestSubStatusCode::Denied,
            'sla_hours' => null,
            'is_start' => false,
            'is_end' => false,
            'assignment_type' => WorkflowAssignmentType::None,
            'assigned_role' => null,
            'allows_skip_to_deliver' => false,
            'targets' => ['CLOSED'],
        ];

        return $steps;
    }

    /**
     * @param  array<string, int>  $stepIdsByCode
     * @param  list<string>  $targetCodes
     */
    private function syncNamedTransitions(int $stepId, array $stepIdsByCode, array $targetCodes): void
    {
        $kept = [];

        foreach ($targetCodes as $code) {
            $toStepId = $stepIdsByCode[$code] ?? null;

            if ($toStepId === null) {
                continue;
            }

            $toStep = WorkflowStep::query()->find($toStepId);
            $status = $toStep?->requestStatus?->code;
            $action = match ($status) {
                RequestStatusCode::Denied => WorkflowTransitionAction::Reject,
                RequestStatusCode::OnHold => WorkflowTransitionAction::Hold,
                RequestStatusCode::Closed => WorkflowTransitionAction::Close,
                default => WorkflowTransitionAction::Advance,
            };

            $transition = WorkflowStepTransition::query()->updateOrCreate(
                [
                    'workflow_step_id' => $stepId,
                    'to_step_id' => $toStepId,
                ],
                [
                    'to_request_status_id' => (int) $toStep?->request_status_id,
                    'action' => $action,
                    'label' => null,
                    'requires_comment' => $action === WorkflowTransitionAction::Reject,
                ],
            );

            $kept[] = $transition->id;
        }

        WorkflowStepTransition::query()
            ->where('workflow_step_id', $stepId)
            ->whereNotIn('id', $kept === [] ? [0] : $kept)
            ->delete();
    }

    /**
     * @return array<string, RequestSubStatusCode>
     */
    private function defaultReasons(): array
    {
        return [
            RequestStatusCode::Submitted->value => RequestSubStatusCode::AwaitingReview,
            RequestStatusCode::Review->value => RequestSubStatusCode::UnderReview,
            RequestStatusCode::Approved->value => RequestSubStatusCode::Approved,
            RequestStatusCode::Delivered->value => RequestSubStatusCode::Delivered,
            RequestStatusCode::Closed->value => RequestSubStatusCode::Closed,
            RequestStatusCode::OnHold->value => RequestSubStatusCode::AwaitingInformation,
            RequestStatusCode::Denied->value => RequestSubStatusCode::Denied,
        ];
    }

    private function defaultSlaHours(RequestStatusCode $stage): ?int
    {
        return match ($stage) {
            RequestStatusCode::Submitted => 48,
            RequestStatusCode::Review => 72,
            RequestStatusCode::Approved => 48,
            default => null,
        };
    }

    private function uniqueCode(Department $department, string $name): string
    {
        $base = Str::upper(Str::slug($name, '_'));
        $base = $base !== '' ? $base : 'WORKFLOW';
        $code = $base;
        $suffix = 2;

        while (Workflow::query()
            ->where('department_id', $department->id)
            ->where('code', $code)
            ->exists()) {
            $code = $base.'_'.$suffix;
            $suffix++;
        }

        return $code;
    }
}
