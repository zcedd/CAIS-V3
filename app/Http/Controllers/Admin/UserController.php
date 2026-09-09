<?php

namespace App\Http\Controllers\Admin;

use App\Enums\RoleName;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\User\DestroyRequest;
use App\Http\Requests\Admin\User\IndexRequest;
use App\Http\Requests\Admin\User\StoreRequest;
use App\Http\Requests\Admin\User\UpdateRequest;
use App\Models\Department;
use App\Models\User;
use App\Services\Admin\UserService;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class UserController extends Controller
{
    public function __construct(
        private UserService $userService,
    ) {}

    public function index(IndexRequest $request): Response
    {
        $search = $request->search();
        $departmentId = $request->departmentId();
        $roles = $request->roles();

        return Inertia::render('admin/users/index', [
            'users' => $this->userService->paginate(
                $search,
                $departmentId,
                $roles,
                $request->sort(),
                $request->direction(),
                $request->perPage(),
            ),
            'departments' => Department::query()
                ->orderBy('name')
                ->get(['id', 'name', 'slug']),
            'role_options' => RoleName::options(),
            'search' => $search,
            'department_id' => $departmentId,
            'role' => $roles,
            'sort' => $request->sort(),
            'direction' => $request->direction(),
        ]);
    }

    public function store(StoreRequest $request): RedirectResponse
    {
        $this->userService->create($request->validated());

        return redirect()
            ->back()
            ->with('success', 'User created. An invite was sent to set their password.');
    }

    public function update(UpdateRequest $request, User $user): RedirectResponse
    {
        $actor = $request->user();

        if (! $actor instanceof User) {
            abort(403);
        }

        $this->userService->update($actor, $user, $request->validated());

        return redirect()
            ->back()
            ->with('success', 'User updated.');
    }

    public function destroy(DestroyRequest $request, User $user): RedirectResponse
    {
        $actor = $request->user();

        if (! $actor instanceof User) {
            abort(403);
        }

        $this->userService->delete($actor, $user);

        return redirect()
            ->back()
            ->with('success', 'User deleted.');
    }
}
