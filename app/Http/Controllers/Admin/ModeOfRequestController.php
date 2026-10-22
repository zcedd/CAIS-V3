<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Lookup\DeleteLookupRequest;
use App\Http\Requests\Admin\Lookup\SaveModeOfRequestRequest;
use App\Models\ModeOfRequest;
use App\Services\Admin\LookupService;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class ModeOfRequestController extends Controller
{
    public function __construct(private LookupService $lookups) {}

    public function index(): Response
    {
        return Inertia::render('admin/modes-of-request/index', [
            'modes' => $this->lookups->modes(),
        ]);
    }

    public function store(SaveModeOfRequestRequest $request): RedirectResponse
    {
        $this->lookups->createMode($request->lookupAttributes());

        return back()->with('success', 'Mode of request saved.');
    }

    public function update(SaveModeOfRequestRequest $request, ModeOfRequest $modeOfRequest): RedirectResponse
    {
        $this->lookups->updateMode($modeOfRequest, $request->lookupAttributes());

        return back()->with('success', 'Mode of request updated.');
    }

    public function destroy(DeleteLookupRequest $request, ModeOfRequest $modeOfRequest): RedirectResponse
    {
        $this->lookups->deleteMode($modeOfRequest);

        return back()->with('success', 'Mode of request deleted.');
    }
}
