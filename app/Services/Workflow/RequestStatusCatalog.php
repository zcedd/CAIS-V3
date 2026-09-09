<?php

namespace App\Services\Workflow;

use App\Enums\RequestStatusCode;
use App\Enums\RequestSubStatusCode;
use App\Models\RequestStatus;
use App\Models\RequestSubStatus;
use Illuminate\Support\Facades\DB;

class RequestStatusCatalog
{
    /**
     * @var array<string, list<string>>
     */
    private const ParentAliases = [
        'draft' => ['Draft'],
        'submitted' => ['Submitted'],
        'review' => ['Review', 'Pending Review'],
        'approved' => ['Approved'],
        'delivered' => ['Delivered'],
        'denied' => ['Denied'],
        'closed' => ['Closed'],
        'on_hold' => ['On Hold'],
    ];

    /**
     * @var array<string, list<string>>
     */
    private const ReasonAliases = [
        'saved_for_later' => ['Saved For Later'],
        'awaiting_review' => ['Awaiting Review'],
        'under_review' => ['Under Review', 'Under Initial Review'],
        'pending_documentation' => ['Pending Documentation'],
        'verified' => ['Verified'],
        'approved' => ['Approved', 'Full Approval'],
        'ready_for_release' => ['Ready for Release'],
        'delivered' => ['Delivered', 'Successfully Delivered'],
        'partially_delivered' => ['Partially Delivered', 'Partially Completed'],
        'denied' => ['Denied', 'Eligibility Denied'],
        'duplicate' => ['Duplicate', 'Duplicate Request'],
        'closed' => ['Closed', 'Closed after Resolution'],
        'awaiting_information' => ['Awaiting Information', 'Waiting for Additional Information'],
    ];

    /**
     * @var list<string>
     */
    private const RetiredParentNames = [
        'Verification',
        'To Deliver',
        'In Progress',
        'Escalated',
        'Beneficiary Confirmation',
        'Pending Review',
    ];

    public function ensure(): void
    {
        foreach (RequestStatusCode::cases() as $code) {
            $this->ensureParent($code);
        }

        foreach (RequestSubStatusCode::cases() as $code) {
            $this->ensureReason($code);
        }

        $this->retireUnusedParents();
        $this->retireUnusedReasons();
        $this->reparentVerifiedReason();
    }

    public function parentId(RequestStatusCode $code): int
    {
        $id = RequestStatus::query()->where('code', $code->value)->value('id');

        if ($id === null) {
            $this->ensure();
            $id = RequestStatus::query()->where('code', $code->value)->value('id');
        }

        return (int) $id;
    }

    public function reasonId(RequestSubStatusCode $code): int
    {
        $id = RequestSubStatus::query()->where('code', $code->value)->value('id');

        if ($id === null) {
            $this->ensure();
            $id = RequestSubStatus::query()->where('code', $code->value)->value('id');
        }

        return (int) $id;
    }

    public function remapOpenAssistances(): void
    {
        $reasonIds = [
            'awaiting_review' => $this->reasonId(RequestSubStatusCode::AwaitingReview),
            'under_review' => $this->reasonId(RequestSubStatusCode::UnderReview),
            'verified' => $this->reasonId(RequestSubStatusCode::Verified),
            'approved' => $this->reasonId(RequestSubStatusCode::Approved),
            'awaiting_information' => $this->reasonId(RequestSubStatusCode::AwaitingInformation),
        ];

        DB::table('assistances')
            ->leftJoin('request_sub_statuses as rss', 'rss.id', '=', 'assistances.current_request_sub_status_id')
            ->leftJoin('request_statuses as rs', 'rs.id', '=', 'rss.request_status_id')
            ->whereNotNull('assistances.current_request_sub_status_id')
            ->where(function ($query): void {
                $query
                    ->whereNull('rs.code')
                    ->orWhere('rs.is_retired', true)
                    ->orWhereIn('rs.name', self::RetiredParentNames);
            })
            ->orderBy('assistances.id')
            ->select([
                'assistances.id',
                'rs.name as parent_name',
                'rss.name as reason_name',
            ])
            ->get()
            ->each(function (object $assistance) use ($reasonIds): void {
                $targetReasonId = $this->remapReasonId(
                    (string) $assistance->parent_name,
                    (string) $assistance->reason_name,
                    $reasonIds,
                );

                if ($targetReasonId === null) {
                    return;
                }

                DB::table('assistances')
                    ->where('id', $assistance->id)
                    ->update(['current_request_sub_status_id' => $targetReasonId]);
            });
    }

    /**
     * @param  array<string, int>  $reasonIds
     */
    private function remapReasonId(string $parentName, string $reasonName, array $reasonIds): ?int
    {
        if ($reasonName === 'Verified') {
            return $reasonIds['verified'];
        }

        if (in_array($parentName, ['To Deliver'], true)) {
            return $reasonIds['approved'];
        }

        if (in_array($parentName, ['On Hold'], true)) {
            return $reasonIds['awaiting_information'];
        }

        if (in_array($parentName, ['Draft'], true) && $reasonName === 'In Progress') {
            return $reasonIds['awaiting_review'];
        }

        if (in_array($parentName, [
            'Pending Review',
            'Verification',
            'In Progress',
            'Escalated',
            'Beneficiary Confirmation',
        ], true)) {
            return $reasonIds['under_review'];
        }

        return null;
    }

    private function ensureParent(RequestStatusCode $code): void
    {
        $aliases = self::ParentAliases[$code->value] ?? [$code->label()];

        $existing = RequestStatus::query()
            ->where('code', $code->value)
            ->orWhereIn('name', $aliases)
            ->orderByRaw('case when code = ? then 0 else 1 end', [$code->value])
            ->first();

        $attributes = [
            'name' => $code->label(),
            'code' => $code->value,
            'sort_order' => $this->sortOrder($code),
            'is_terminal' => $code->isTerminal(),
            'is_hold' => $code->isHold(),
            'pauses_sla' => $code->pausesSla(),
            'is_retired' => false,
        ];

        if ($existing instanceof RequestStatus) {
            $existing->fill($attributes)->save();

            return;
        }

        RequestStatus::query()->create($attributes);
    }

    private function ensureReason(RequestSubStatusCode $code): void
    {
        $parentId = $this->parentId($code->parent());
        $aliases = self::ReasonAliases[$code->value] ?? [$code->label()];

        $existing = RequestSubStatus::query()
            ->where('code', $code->value)
            ->orWhereIn('name', $aliases)
            ->orderByRaw('case when code = ? then 0 else 1 end', [$code->value])
            ->first();

        $attributes = [
            'name' => $code->label(),
            'code' => $code->value,
            'request_status_id' => $parentId,
            'description' => null,
            'is_retired' => false,
        ];

        if ($existing instanceof RequestSubStatus) {
            $existing->fill($attributes)->save();

            return;
        }

        RequestSubStatus::query()->create($attributes);
    }

    private function retireUnusedParents(): void
    {
        RequestStatus::query()
            ->where(function ($query): void {
                $query
                    ->whereNotIn('code', RequestStatusCode::values())
                    ->orWhereNull('code');
            })
            ->whereNotIn('name', array_map(
                static fn (RequestStatusCode $code): string => $code->label(),
                RequestStatusCode::cases(),
            ))
            ->update(['is_retired' => true]);
    }

    private function retireUnusedReasons(): void
    {
        RequestSubStatus::query()
            ->where(function ($query): void {
                $query
                    ->whereNotIn('code', RequestSubStatusCode::values())
                    ->orWhereNull('code');
            })
            ->whereNotIn('name', array_map(
                static fn (RequestSubStatusCode $code): string => $code->label(),
                RequestSubStatusCode::cases(),
            ))
            ->update(['is_retired' => true]);
    }

    private function reparentVerifiedReason(): void
    {
        $reviewId = $this->parentId(RequestStatusCode::Review);

        RequestSubStatus::query()
            ->where('code', RequestSubStatusCode::Verified->value)
            ->update(['request_status_id' => $reviewId]);
    }

    private function sortOrder(RequestStatusCode $code): int
    {
        return match ($code) {
            RequestStatusCode::Draft => 10,
            RequestStatusCode::Submitted => 20,
            RequestStatusCode::Review => 30,
            RequestStatusCode::Approved => 40,
            RequestStatusCode::OnHold => 50,
            RequestStatusCode::Delivered => 60,
            RequestStatusCode::Denied => 70,
            RequestStatusCode::Closed => 80,
        };
    }
}
