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
        };
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
            self::Approved, self::ReadyForRelease => RequestStatusCode::Approved,
            self::Delivered, self::PartiallyDelivered => RequestStatusCode::Delivered,
            self::Denied, self::Duplicate => RequestStatusCode::Denied,
            self::Closed => RequestStatusCode::Closed,
            self::AwaitingInformation => RequestStatusCode::OnHold,
        };
    }
}
