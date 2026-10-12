<?php

namespace App\Enums;

use App\Models\RequestSubStatus;

enum RequestSubStatusCode: string
{
    case SavedForLater = 'saved_for_later';
    case AwaitingReview = 'awaiting_review';
    case UnderReview = 'under_review';
    case PendingDocumentation = 'pending_documentation';
    case Verified = 'verified';
    case Approved = 'approved';
    case ReadyForRelease = 'ready_for_release';
    case Delivered = 'delivered';
    case PartiallyDelivered = 'partially_delivered';
    case Denied = 'denied';
    case Duplicate = 'duplicate';
    case Closed = 'closed';
    case AwaitingInformation = 'awaiting_information';
    case DistributionDraft = 'distribution_draft';
    case ForDistributionValidation = 'for_distribution_validation';
    case ForDistributionApproval = 'for_distribution_approval';
    case DistributionReturned = 'distribution_returned';
    case DistributionRejected = 'distribution_rejected';
    case ScheduledForRelease = 'scheduled_for_release';
    case ReleaseInProgress = 'release_in_progress';
    case DistributionCancelled = 'distribution_cancelled';
    case Absent = 'absent';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    public function label(): string
    {
        return match ($this) {
            self::SavedForLater => 'Saved For Later',
            self::AwaitingReview => 'Awaiting Review',
            self::UnderReview => 'Under Review',
            self::PendingDocumentation => 'Pending Documentation',
            self::Verified => 'Verified',
            self::Approved => 'Approved',
            self::ReadyForRelease => 'Ready for Release',
            self::Delivered => 'Delivered',
            self::PartiallyDelivered => 'Partially Delivered',
            self::Denied => 'Denied',
            self::Duplicate => 'Duplicate',
            self::Closed => 'Closed',
            self::AwaitingInformation => 'Awaiting Information',
            self::DistributionDraft => 'Distribution draft',
            self::ForDistributionValidation => 'For distribution validation',
            self::ForDistributionApproval => 'For distribution approval',
            self::DistributionReturned => 'Distribution returned',
            self::DistributionRejected => 'Distribution rejected',
            self::ScheduledForRelease => 'Scheduled for release',
            self::ReleaseInProgress => 'Release in progress',
            self::DistributionCancelled => 'Distribution cancelled',
            self::Absent => 'Absent',
        };
    }

    /**
     * Statuses written from a distribution batch, other than delivery itself.
     *
     * @return list<self>
     */
    public static function distributionProgress(): array
    {
        return [
            self::DistributionDraft,
            self::ForDistributionValidation,
            self::ForDistributionApproval,
            self::DistributionReturned,
            self::DistributionRejected,
            self::ScheduledForRelease,
            self::ReleaseInProgress,
            self::DistributionCancelled,
            self::Absent,
        ];
    }

    /**
     * @return list<string>
     */
    public static function distributionProgressValues(): array
    {
        return array_map(
            static fn (self $code): string => $code->value,
            self::distributionProgress(),
        );
    }

    public function isDistributionProgress(): bool
    {
        return in_array($this, self::distributionProgress(), true);
    }

    public function reflectsOnTask(): bool
    {
        return $this->isDistributionProgress()
            || in_array($this, [
                self::ReadyForRelease,
                self::Delivered,
                self::PartiallyDelivered,
            ], true);
    }

    public function matches(?RequestSubStatus $subStatus): bool
    {
        if ($subStatus === null) {
            return false;
        }

        if ($subStatus->code === $this) {
            return true;
        }

        return $subStatus->name === $this->label()
            || ($this === self::Delivered && $subStatus->name === 'Successfully Delivered')
            || ($this === self::PartiallyDelivered && $subStatus->name === 'Partially Completed')
            || ($this === self::Approved && $subStatus->name === 'Full Approval')
            || ($this === self::Duplicate && $subStatus->name === 'Duplicate Request')
            || ($this === self::UnderReview && $subStatus->name === 'Under Initial Review');
    }

    public function parent(): RequestStatusCode
    {
        return match ($this) {
            self::SavedForLater => RequestStatusCode::Draft,
            self::AwaitingReview => RequestStatusCode::Submitted,
            self::UnderReview, self::PendingDocumentation, self::Verified => RequestStatusCode::Review,
            self::Approved,
            self::ReadyForRelease,
            self::ForDistributionApproval,
            self::ScheduledForRelease,
            self::ReleaseInProgress,
            self::Absent => RequestStatusCode::Approved,
            self::Delivered, self::PartiallyDelivered => RequestStatusCode::Delivered,
            self::Denied, self::Duplicate => RequestStatusCode::Denied,
            self::Closed => RequestStatusCode::Closed,
            self::AwaitingInformation => RequestStatusCode::OnHold,
            self::DistributionDraft,
            self::ForDistributionValidation,
            self::DistributionReturned,
            self::DistributionRejected,
            self::DistributionCancelled => RequestStatusCode::Review,
        };
    }
}
