<?php

namespace App\Http\Requests\Admin\Lookup;

use App\Models\Sector;

class SaveSectorRequest extends SaveLookupRequest
{
    protected function lookupTable(): string
    {
        return 'sectors';
    }

    protected function softDeletes(): bool
    {
        return true;
    }

    protected function ignoredId(): ?int
    {
        $sector = $this->route('sector');

        return $sector instanceof Sector ? $sector->id : null;
    }
}
