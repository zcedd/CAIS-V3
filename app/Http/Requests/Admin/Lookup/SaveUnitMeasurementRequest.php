<?php

namespace App\Http\Requests\Admin\Lookup;

use App\Models\ItemUnitMeasurement;

class SaveUnitMeasurementRequest extends SaveLookupRequest
{
    protected function lookupTable(): string
    {
        return 'item_unit_measurements';
    }

    protected function ignoredId(): ?int
    {
        $unit = $this->route('unitMeasurement');

        return $unit instanceof ItemUnitMeasurement ? $unit->id : null;
    }
}
