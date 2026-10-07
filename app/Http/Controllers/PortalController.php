<?php

namespace App\Http\Controllers;

use App\Enums\RoleName;
use App\Http\Requests\Portal\EnterOfficeRequest;
use App\Models\Department;
use App\Models\User;
use App\Services\Auth\OfficePortal;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class PortalController extends Controller
{
    public function __construct(private OfficePortal $portal) {}

    public function show(Request $request): Response|RedirectResponse
    {
        $user = $request->user();

        if (! $user instanceof User) {
            abort(403);
        }

        $offices = $this->portal->allowedOffices($user);

        if (count($offices) < 2) {
            if (count($offices) === 1) {
                return $this->portal->homeRedirect($user, $offices[0])
                    ?? $this->missingDepartment();
            }

            return redirect()->route('dashboard');
        }

        return Inertia::render('portal/index', [
            'offices' => $this->portal->cards($user),
            'departments' => $user->department_id === null
                ? Department::query()->orderBy('name')->get(['id', 'name'])
                : [],
        ]);
    }

    public function enter(EnterOfficeRequest $request): RedirectResponse
    {
        $user = $request->user();

        if (! $user instanceof User) {
            abort(403);
        }

        $office = RoleName::from($request->string('office')->toString());
        $department = $user->department;

        if ($department === null && $request->filled('department_id')) {
            $department = Department::query()->find($request->integer('department_id'));
        }

        return $this->portal->enter($user, $office, $department);
    }

    private function missingDepartment(): Response
    {
        return Inertia::render('dashboard', [
            'noDepartment' => true,
        ]);
    }
}
