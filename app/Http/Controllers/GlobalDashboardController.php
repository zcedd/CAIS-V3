<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class GlobalDashboardController extends Controller
{
    /**
     * Redirect authenticated users to their department dashboard, or show an empty state.
     */
    public function __invoke(Request $request): Response|RedirectResponse
    {
        $department = $request->user()?->loadMissing('department')->department;

        if ($department !== null) {
            return redirect()->route('user.dashboard.index', $department);
        }

        return Inertia::render('dashboard', [
            'noDepartment' => true,
        ]);
    }
}
