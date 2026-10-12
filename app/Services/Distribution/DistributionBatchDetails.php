<?php

namespace App\Services\Distribution;

use App\Enums\DistributionItemApprovalStatus;
use App\Models\DistributionBatch;
use App\Models\DistributionBatchItem;

class DistributionBatchDetails
{
    public function __construct(private DistributionReconciliation $reconciliation) {}

    /**
     * @return array<string, mixed>
     */
    public function payload(DistributionBatch $batch): array
    {
        $batch->load([
            'program:id,name,department_id',
            'department:id,name,slug',
            'creator:id,firstName,lastName',
            'policy.stages.user:id,firstName,lastName',
            'items.beneficiary:id,name,cais_number',
            'items.assistance.currentRequestSubStatus:id,name,code',
            'items.lines.item:id,name,kind',
            'items.reviewer:id,firstName,lastName',
            'items.releases.releasedBy:id,firstName,lastName',
            'tasks.stage',
            'decisions.actor:id,firstName,lastName',
            'documents',
            'revisions',
        ]);

        return [
            'id' => $batch->id,
            'batch_code' => $batch->batch_code,
            'title' => $batch->title,
            'description' => $batch->description,
            'venue' => $batch->venue,
            'venue_details' => $batch->venue_details,
            'venue_latitude' => $batch->venue_latitude,
            'venue_longitude' => $batch->venue_longitude,
            'scheduled_at' => $batch->scheduled_at?->toIso8601String(),
            'opened_at' => $batch->opened_at?->toIso8601String(),
            'closed_at' => $batch->closed_at?->toIso8601String(),
            'approval_mode' => $batch->approval_mode->value,
            'approval_mode_label' => $batch->approval_mode->label(),
            'status' => $batch->status->value,
            'status_label' => $batch->status->label(),
            'revision' => $batch->revision,
            'lock_version' => $batch->lock_version,
            'finalization_scope' => $batch->finalization_scope?->value,
            'finalization_label' => $batch->finalization_scope?->label(),
            'notes' => $batch->notes,
            'submitted_at' => $batch->submitted_at?->toIso8601String(),
            'approved_at' => $batch->approved_at?->toIso8601String(),
            'locked_at' => $batch->locked_at?->toIso8601String(),
            'completed_at' => $batch->completed_at?->toIso8601String(),
            'cancelled_at' => $batch->cancelled_at?->toIso8601String(),
            'cancellation_reason' => $batch->cancellation_reason,
            'beneficiary_count' => $batch->beneficiary_count,
            'approved_beneficiary_count' => $batch->approved_beneficiary_count,
            'proposed_cash_total' => $batch->proposed_cash_total,
            'approved_cash_total' => $batch->approved_cash_total,
            'validation_summary' => $batch->validation_summary,
            'program' => [
                'id' => $batch->program?->id,
                'name' => $batch->program?->name,
            ],
            'department' => [
                'id' => $batch->department?->id,
                'name' => $batch->department?->name,
                'slug' => $batch->department?->slug,
            ],
            'creator' => [
                'id' => $batch->creator?->id,
                'name' => $batch->creator?->name,
            ],
            'policy' => $batch->policy === null ? null : [
                'id' => $batch->policy->id,
                'name' => $batch->policy->name,
                'strategy' => $batch->policy->strategy->value,
                'allow_partial_finalization' => $batch->policy->allow_partial_finalization,
                'allow_amount_modification' => $batch->policy->allow_amount_modification,
                'allow_resubmit_after_reject' => $batch->policy->allow_resubmit_after_reject,
                'allow_self_approval' => $batch->policy->allow_self_approval,
                'reschedule_requires_reapproval' => $batch->policy->reschedule_requires_reapproval,
                'stages' => $batch->policy->stages->map(fn ($stage): array => [
                    'id' => $stage->id,
                    'sequence' => $stage->sequence,
                    'name' => $stage->name,
                    'role_name' => $stage->role_name,
                    'user_name' => $stage->user?->name,
                ])->all(),
            ],
            'items' => $batch->items->map(fn (DistributionBatchItem $item): array => [
                'id' => $item->id,
                'assistance_id' => $item->assistance_id,
                'approval_status' => $item->approval_status->value,
                'approval_label' => $item->approval_status->label(),
                'review_remarks' => $item->review_remarks,
                'exclusion_reason' => $item->exclusion_reason,
                'reviewed_at' => $item->reviewed_at?->toIso8601String(),
                'reviewer' => $item->reviewer?->name,
                'warnings' => $item->warnings ?? [],
                'beneficiary' => [
                    'id' => $item->beneficiary?->id,
                    'name' => $item->beneficiary?->name,
                    'cais_number' => $item->beneficiary?->cais_number,
                ],
                'request_status' => $item->assistance?->currentRequestSubStatus?->name,
                'lines' => $item->lines->map(fn ($line): array => [
                    'id' => $line->id,
                    'item_id' => $line->item_id,
                    'item_name' => $line->item?->name,
                    'kind' => $line->item?->kind?->value,
                    'proposed_quantity' => $line->proposed_quantity,
                    'approved_quantity' => $line->approved_quantity,
                    'proposed_amount' => $line->proposed_amount,
                    'approved_amount' => $line->approved_amount,
                ])->all(),
                'releases' => $item->releases->map(fn ($release): array => [
                    'id' => $release->id,
                    'line_id' => $release->distribution_batch_item_line_id,
                    'outcome' => $release->outcome->value,
                    'actual_quantity' => $release->actual_quantity,
                    'actual_amount' => $release->actual_amount,
                    'released_at' => $release->released_at?->toIso8601String(),
                    'receipt_reference' => $release->receipt_reference,
                    'identity_verified' => $release->identity_verified,
                    'remarks' => $release->remarks,
                    'released_by' => $release->releasedBy?->name,
                ])->all(),
                'distributable' => $item->approval_status === DistributionItemApprovalStatus::Approved,
            ])->all(),
            'tasks' => $batch->tasks->map(fn ($task): array => [
                'id' => $task->id,
                'revision' => $task->revision,
                'status' => $task->status->value,
                'stage' => $task->stage?->name,
                'sequence' => $task->stage?->sequence,
                'completed_at' => $task->completed_at?->toIso8601String(),
            ])->all(),
            'decisions' => $batch->decisions->map(fn ($decision): array => [
                'id' => $decision->id,
                'revision' => $decision->revision,
                'item_id' => $decision->distribution_batch_item_id,
                'decision' => $decision->decision->value,
                'previous_decision' => $decision->previous_decision,
                'previous_amount' => $decision->previous_amount,
                'new_amount' => $decision->new_amount,
                'previous_quantity' => $decision->previous_quantity,
                'new_quantity' => $decision->new_quantity,
                'remarks' => $decision->remarks,
                'actor' => $decision->actor?->name,
                'created_at' => $decision->created_at?->toIso8601String(),
            ])->all(),
            'documents' => $batch->documents->map(fn ($document): array => [
                'id' => $document->id,
                'original_name' => $document->original_name,
                'notes' => $document->notes,
            ])->all(),
            'revisions' => $batch->revisions->map(fn ($revision): array => [
                'revision' => $revision->revision,
                'created_at' => $revision->created_at?->toIso8601String(),
            ])->all(),
            'reconciliation' => $this->reconciliation->summarize($batch),
        ];
    }
}
