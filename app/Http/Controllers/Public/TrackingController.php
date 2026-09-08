<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Http\Requests\Public\Tracking\LookupRequest;
use App\Services\Public\PublicIntakeService;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class TrackingController extends Controller
{
    public function __construct(
        private PublicIntakeService $publicIntakeService,
    ) {}

    public function index(): Response
    {
        return Inertia::render('public/track/index');
    }

    public function lookup(LookupRequest $request): RedirectResponse|Response
    {
        $validated = $request->validated();

        $result = $this->publicIntakeService->track(
            $validated['cais_number'],
            $validated['last_name'],
        );

        return Inertia::render('public/track/show', [
            ...$result,
            'last_name' => $validated['last_name'],
        ]);
    }
}
