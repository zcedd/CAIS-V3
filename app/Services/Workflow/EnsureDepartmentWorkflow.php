<?php

namespace App\Services\Workflow;

use App\Enums\RequestStatusCode;
use App\Enums\RequestSubStatusCode;
use App\Enums\WorkflowTemplate;
use App\Models\Department;
use App\Models\Workflow;
use App\Models\WorkflowStep;
use App\Models\WorkflowStepTransition;

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
            'name' => $name ?? $template->label(),
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
        $happyPath = $template->happyPath();
        $defaultReasons = $this->defaultReasons();

        $stepIdsByCode = [];

        foreach ($happyPath as $index => $stage) {
            $step = WorkflowStep::query()->updateOrCreate(
                [
                    'workflow_id' => $workflow->id,
                    'request_status_id' => $this->catalog->parentId($stage),
                ],
                [
                    'sort_order' => ($index + 1) * 10,
                    'default_request_sub_status_id' => $this->catalog->reasonId($defaultReasons[$stage->value]),
                    'sla_hours' => $this->defaultSlaHours($stage),
                    'requires_assignee' => $stage === RequestStatusCode::Review,
                    'permission' => null,
                    'allows_skip_to_deliver' => $template === WorkflowTemplate::WalkIn
                        && $stage === RequestStatusCode::Submitted,
                ],
            );

            $stepIdsByCode[$stage->value] = $step->id;
        }

        $parkingStages = [
            RequestStatusCode::OnHold,
            RequestStatusCode::Denied,
        ];

        foreach ($parkingStages as $offset => $stage) {
            if (isset($stepIdsByCode[$stage->value])) {
                continue;
            }

            $step = WorkflowStep::query()->updateOrCreate(
                [
                    'workflow_id' => $workflow->id,
                    'request_status_id' => $this->catalog->parentId($stage),
                ],
                [
                    'sort_order' => 90 + $offset,
                    'default_request_sub_status_id' => $this->catalog->reasonId($defaultReasons[$stage->value]),
                    'sla_hours' => null,
                    'requires_assignee' => false,
                    'permission' => null,
                    'allows_skip_to_deliver' => false,
                ],
            );

            $stepIdsByCode[$stage->value] = $step->id;
        }

        WorkflowStep::query()
            ->where('workflow_id', $workflow->id)
            ->whereNotIn('id', array_values($stepIdsByCode))
            ->delete();

        foreach ($happyPath as $index => $stage) {
            $stepId = $stepIdsByCode[$stage->value];
            $next = $happyPath[$index + 1] ?? null;
            $targets = [];

            if ($next instanceof RequestStatusCode) {
                $targets[] = $next;
            }

            if (! $stage->isTerminal()) {
                $targets[] = RequestStatusCode::OnHold;
                $targets[] = RequestStatusCode::Denied;

                if ($stage !== RequestStatusCode::Closed) {
                    $targets[] = RequestStatusCode::Closed;
                }
            }

            if ($stage === RequestStatusCode::Delivered) {
                $targets[] = RequestStatusCode::Denied;
            }

            if ($template === WorkflowTemplate::WalkIn && $stage === RequestStatusCode::Submitted) {
                $targets[] = RequestStatusCode::Delivered;
            }

            $this->syncTransitions($stepId, $targets);
        }

        $resumeStage = $happyPath[0];

        foreach ($happyPath as $stage) {
            if (! $stage->isTerminal()) {
                $resumeStage = $stage;
            }
        }

        $this->syncTransitions($stepIdsByCode[RequestStatusCode::OnHold->value], [
            $resumeStage,
            RequestStatusCode::Denied,
            RequestStatusCode::Closed,
        ]);

        $this->syncTransitions($stepIdsByCode[RequestStatusCode::Denied->value], [
            RequestStatusCode::Closed,
        ]);
    }

    /**
     * @param  list<RequestStatusCode>  $targets
     */
    private function syncTransitions(int $stepId, array $targets): void
    {
        $targetIds = array_unique(array_map(
            fn (RequestStatusCode $code): int => $this->catalog->parentId($code),
            $targets,
        ));

        WorkflowStepTransition::query()
            ->where('workflow_step_id', $stepId)
            ->whereNotIn('to_request_status_id', $targetIds)
            ->delete();

        foreach ($targetIds as $targetId) {
            WorkflowStepTransition::query()->firstOrCreate([
                'workflow_step_id' => $stepId,
                'to_request_status_id' => $targetId,
            ]);
        }
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
            RequestStatusCode::Delivered => null,
            RequestStatusCode::Closed => null,
            default => null,
        };
    }
}
