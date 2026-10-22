<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Lookup\DeleteLookupRequest;
use App\Http\Requests\Admin\Lookup\SaveSectorRequest;
use App\Models\Sector;
use App\Services\Admin\LookupService;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class SectorController extends Controller
{
    public function __construct(private LookupService $lookups) {}

    public function index(): Response
    {
        return Inertia::render('admin/sectors/index', [
            'sectors' => $this->lookups->sectors(),
        ]);
    }

    public function store(SaveSectorRequest $request): RedirectResponse
    {
        $this->lookups->createSector($request->lookupAttributes());

        return back()->with('success', 'Sector saved.');
    }

    public function update(SaveSectorRequest $request, Sector $sector): RedirectResponse
    {
        $this->lookups->updateSector($sector, $request->lookupAttributes());

        return back()->with('success', 'Sector updated.');
    }

    public function destroy(DeleteLookupRequest $request, Sector $sector): RedirectResponse
    {
        $this->lookups->deleteSector($sector);

        return back()->with('success', 'Sector deleted.');
    }
}
