<?php

namespace App\Http\Controllers;

use App\Enums\RoleName;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class GlobalDashboardController extends Controller
{
    /**
     * Redirect authenticated users to their department dashboard, executives without a department to the executive dashboard, administrators without a department to their program queue, or show an empty state.
     */
    public function __invoke(Request $request): Response|RedirectResponse
    {
        $user = $request->user();
        $department = $user?->loadMissing('department')->department;

        if ($department !== null) {
            return redirect()->route('user.dashboard.index', $department);
        }

        if ($user instanceof User && $user->hasRole(RoleName::Executive)) {
            return redirect()->route('executive.dashboard');
        }

        if ($user instanceof User && $user->hasRole(RoleName::Administrator)) {
            return redirect()->route('administrator.programs.index');
        }

        return Inertia::render('dashboard', [
            'noDepartment' => true,
        ]);
    }
}
