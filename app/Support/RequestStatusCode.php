<?php

namespace App\Support;

use App\Models\RequestStatus;

enum RequestStatusCode: string
{
    case Draft = 'draft';
    case Submitted = 'submitted';
    case Review = 'review';
    case Approved = 'approved';
    case Delivered = 'delivered';
    case Denied = 'denied';
    case Closed = 'closed';
    case OnHold = 'on_hold';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_map(
            static fn (self $code): string => $code->value,
            self::cases(),
        );
    }

    /**
     * @return list<string>
     */
    public static function terminalValues(): array
    {
        return [
            self::Delivered->value,
            self::Denied->value,
            self::Closed->value,
        ];
    }

    public function isTerminal(): bool
    {
        return in_array($this->value, self::terminalValues(), true);
    }

    public function isHold(): bool
    {
        return $this === self::OnHold;
    }

    public function pausesSla(): bool
    {
        return $this === self::OnHold;
    }

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Draft',
            self::Submitted => 'Submitted',
            self::Review => 'Review',
            self::Approved => 'Approved',
            self::Delivered => 'Delivered',
            self::Denied => 'Denied',
            self::Closed => 'Closed',
            self::OnHold => 'On Hold',
        };
    }

    public function matches(?RequestStatus $status): bool
    {
        if ($status === null) {
            return false;
        }

        if ($status->code === $this) {
            return true;
        }

        return $status->name === $this->label();
    }

    /**
     * @param  list<self>  $codes
     */
    public static function matchesAny(?RequestStatus $status, array $codes): bool
    {
        foreach ($codes as $code) {
            if ($code->matches($status)) {
                return true;
            }
        }

        return false;
    }
}
