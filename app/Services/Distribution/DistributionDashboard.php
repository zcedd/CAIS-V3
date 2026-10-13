<?php

namespace App\Services\Distribution;

use App\Enums\DistributionBatchStatus;
use App\Enums\DistributionTransactionStatus;
use App\Enums\DistributionType;
use App\Models\Department;
use App\Models\DistributionBatch;
use App\Models\DistributionBatchItem;

class DistributionDashboard
{
    /**
     * @return array{
     *     total: int,
     *     individual: int,
     *     batch: int,
     *     scheduled: int,
     *     pending_approval: int,
     *     in_progress: int,
     *     completed: int,
     *     cancelled: int,
     *     beneficiaries_scheduled: int,
     *     beneficiaries_released: int
     * }
     */
    public function summarize(Department $department): array
    {
        $counts = DistributionBatch::query()
            ->where('department_id', $department->id)
            ->toBase()
            ->selectRaw('count(*) as total')
            ->selectRaw('sum(case when distribution_type = ? then 1 else 0 end) as individual_count', [DistributionType::Individual->value])
            ->selectRaw('sum(case when distribution_type = ? then 1 else 0 end) as batch_count', [DistributionType::Batch->value])
            ->selectRaw('sum(case when status = ? then 1 else 0 end) as scheduled_count', [DistributionBatchStatus::Scheduled->value])
            ->selectRaw('sum(case when status = ? then 1 else 0 end) as pending_approval_count', [DistributionBatchStatus::ForApproval->value])
            ->selectRaw('sum(case when status = ? then 1 else 0 end) as in_progress_count', [DistributionBatchStatus::InProgress->value])
            ->selectRaw('sum(case when status = ? then 1 else 0 end) as completed_count', [DistributionBatchStatus::Completed->value])
            ->selectRaw('sum(case when status = ? then 1 else 0 end) as cancelled_count', [DistributionBatchStatus::Cancelled->value])
            ->selectRaw(
                'sum(case when status in (?, ?, ?, ?) then beneficiary_count else 0 end) as beneficiaries_scheduled',
                [
                    DistributionBatchStatus::Approved->value,
                    DistributionBatchStatus::Scheduled->value,
                    DistributionBatchStatus::InProgress->value,
                    DistributionBatchStatus::ForApproval->value,
                ],
            )
            ->first();

        $released = DistributionBatchItem::query()
            ->whereHas('batch', fn ($query) => $query->where('department_id', $department->id))
            ->whereHas('transactions', fn ($query) => $query->where('status', DistributionTransactionStatus::Finalized))
            ->count();

        return [
            'total' => (int) ($counts->total ?? 0),
            'individual' => (int) ($counts->individual_count ?? 0),
            'batch' => (int) ($counts->batch_count ?? 0),
            'scheduled' => (int) ($counts->scheduled_count ?? 0),
            'pending_approval' => (int) ($counts->pending_approval_count ?? 0),
            'in_progress' => (int) ($counts->in_progress_count ?? 0),
            'completed' => (int) ($counts->completed_count ?? 0),
            'cancelled' => (int) ($counts->cancelled_count ?? 0),
            'beneficiaries_scheduled' => (int) ($counts->beneficiaries_scheduled ?? 0),
            'beneficiaries_released' => $released,
        ];
    }
}
