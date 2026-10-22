<?php

namespace App\Http\Requests\Admin\Lookup;

use App\Models\ModeOfRequest;

class SaveModeOfRequestRequest extends SaveLookupRequest
{
    protected function lookupTable(): string
    {
        return 'mode_of_requests';
    }

    protected function ignoredId(): ?int
    {
        $mode = $this->route('modeOfRequest');

        return $mode instanceof ModeOfRequest ? $mode->id : null;
    }
}
