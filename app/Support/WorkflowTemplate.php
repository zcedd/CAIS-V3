<?php

namespace App\Support;

enum WorkflowTemplate: string
{
    case Standard = 'standard';
    case WalkIn = 'walk_in';
    case Custom = 'custom';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_map(
            static fn (self $template): string => $template->value,
            self::cases(),
        );
    }

    public function label(): string
    {
        return match ($this) {
            self::Standard => 'Standard',
            self::WalkIn => 'Walk-in',
            self::Custom => 'Custom',
        };
    }

    /**
     * Happy-path stages in order. On Hold, Denied, and Closed are attached as transitions.
     *
     * @return list<RequestStatusCode>
     */
    public function happyPath(): array
    {
        return match ($this) {
            self::Standard => [
                RequestStatusCode::Submitted,
                RequestStatusCode::Review,
                RequestStatusCode::Approved,
                RequestStatusCode::Delivered,
                RequestStatusCode::Closed,
            ],
            self::WalkIn => [
                RequestStatusCode::Submitted,
                RequestStatusCode::Delivered,
                RequestStatusCode::Closed,
            ],
            self::Custom => [
                RequestStatusCode::Submitted,
                RequestStatusCode::Review,
                RequestStatusCode::Approved,
                RequestStatusCode::Delivered,
                RequestStatusCode::Closed,
            ],
        };
    }
}
