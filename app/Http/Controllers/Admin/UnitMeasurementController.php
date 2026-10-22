<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Lookup\DeleteLookupRequest;
use App\Http\Requests\Admin\Lookup\SaveUnitMeasurementRequest;
use App\Models\ItemUnitMeasurement;
use App\Services\Admin\LookupService;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class UnitMeasurementController extends Controller
{
    public function __construct(private LookupService $lookups) {}

    public function index(): Response
    {
        return Inertia::render('admin/unit-measurements/index', [
            'units' => $this->lookups->units(),
        ]);
    }

    public function store(SaveUnitMeasurementRequest $request): RedirectResponse
    {
        $this->lookups->createUnit($request->lookupAttributes());

        return back()->with('success', 'Unit saved.');
    }

    public function update(SaveUnitMeasurementRequest $request, ItemUnitMeasurement $unitMeasurement): RedirectResponse
    {
        $this->lookups->updateUnit($unitMeasurement, $request->lookupAttributes());

        return back()->with('success', 'Unit updated.');
    }

    public function destroy(DeleteLookupRequest $request, ItemUnitMeasurement $unitMeasurement): RedirectResponse
    {
        $this->lookups->deleteUnit($unitMeasurement);

        return back()->with('success', 'Unit deleted.');
    }
}
