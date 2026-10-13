<?php

namespace App\Actions\Distribution;

use App\Enums\RequestStatusCode;
use App\Models\Assistance;
use App\Models\RequestSubStatus;
use Illuminate\Validation\ValidationException;

class AssertDeliveredRequiresDistribution
{
    public const MESSAGE = 'Complete an individual or batch distribution before marking this request delivered.';

    public function __invoke(Assistance $assistance, RequestSubStatus $target): void
    {
        $target->loadMissing('requestStatus');

        if (! RequestStatusCode::Delivered->matches($target->requestStatus)) {
            return;
        }

        throw ValidationException::withMessages([
            'request_sub_status_id' => self::MESSAGE,
        ]);
    }
}
