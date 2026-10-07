<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\Auth\OfficePortal;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class GlobalDashboardController extends Controller
{
    public function __construct(private OfficePortal $portal) {}

    /**
     * Send each account to its office, or to the portal when more than one office is allowed.
     */
    public function __invoke(Request $request): Response|RedirectResponse
    {
        $user = $request->user();

        if (! $user instanceof User) {
            abort(403);
        }

        $offices = $this->portal->allowedOffices($user);

        if (count($offices) > 1) {
            $active = $this->portal->activeOffice($user);

            if ($active !== null) {
                return $this->portal->homeRedirect($user, $active)
                    ?? $this->missingDepartment();
            }

            return redirect()->route('portal');
        }

        if (count($offices) === 1) {
            return $this->portal->homeRedirect($user, $offices[0])
                ?? $this->missingDepartment();
        }

        $department = $user->loadMissing('department')->department;

        if ($department !== null) {
            return redirect()->route('user.dashboard.index', $department);
        }

        return $this->missingDepartment();
    }

    private function missingDepartment(): Response
    {
        return Inertia::render('dashboard', [
            'noDepartment' => true,
        ]);
    }
}
