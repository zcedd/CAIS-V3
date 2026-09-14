<?php

namespace App\Services\Admin;

use App\Enums\RoleName;
use App\Models\Department;
use App\Models\User;
use App\Notifications\UserInvitedNotification;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class UserService
{
    private const DEFAULT_PER_PAGE = 15;

    /** @var list<string> */
    private const SORTABLE_COLUMNS = ['name', 'email', 'department'];

    /**
     * @param  list<string>  $roles
     * @return LengthAwarePaginator<int, array<string, mixed>>
     */
    public function paginate(
        string $search,
        ?int $departmentId,
        array $roles,
        string $sort,
        string $direction,
        int $perPage,
    ): LengthAwarePaginator {
        $sortColumn = in_array($sort, self::SORTABLE_COLUMNS, true) ? $sort : 'name';
        $sortDirection = $direction === 'asc' ? 'asc' : 'desc';

        $query = User::query()
            ->with(['department:id,name,slug', 'roles:id,name'])
            ->when($search !== '', function ($query) use ($search): void {
                $like = '%'.$search.'%';

                $query->where(function ($query) use ($like): void {
                    $query->where('firstName', 'like', $like)
                        ->orWhere('middleName', 'like', $like)
                        ->orWhere('lastName', 'like', $like)
                        ->orWhere('email', 'like', $like);
                });
            })
            ->when($departmentId !== null, fn ($query) => $query->where('department_id', $departmentId))
            ->when($roles !== [], fn ($query) => $query->role($roles));

        if ($sortColumn === 'department') {
            $query->orderBy(
                Department::query()
                    ->select('name')
                    ->whereColumn('departments.id', 'users.department_id')
                    ->limit(1),
                $sortDirection,
            );
        } elseif ($sortColumn === 'email') {
            $query->orderBy('email', $sortDirection);
        } else {
            $query->orderBy('firstName', $sortDirection)
                ->orderBy('lastName', $sortDirection);
        }

        return $query
            ->paginate($perPage > 0 ? $perPage : self::DEFAULT_PER_PAGE)
            ->withQueryString()
            ->through(fn (User $user): array => $this->serialize($user));
    }

    /**
     * @param  array{
     *     firstName: string,
     *     middleName?: string|null,
     *     lastName: string,
     *     email: string,
     *     department_id?: int|null,
     *     roles?: list<string>
     * }  $validated
     */
    public function create(array $validated): User
    {
        $user = DB::transaction(function () use ($validated): User {
            $user = User::query()->create([
                'firstName' => $validated['firstName'],
                'middleName' => $validated['middleName'] ?? null,
                'lastName' => $validated['lastName'],
                'email' => $validated['email'],
                'password' => Str::password(),
                'department_id' => $validated['department_id'] ?? null,
            ]);

            $user->forceFill([
                'email_verified_at' => now(),
            ])->save();

            $user->syncRoles($validated['roles'] ?? []);

            return $user;
        });

        $token = Password::broker()->createToken($user);
        $user->notify(new UserInvitedNotification($token));

        return $user;
    }

    /**
     * @param  array{
     *     firstName: string,
     *     middleName?: string|null,
     *     lastName: string,
     *     email: string,
     *     password?: string|null,
     *     department_id?: int|null,
     *     roles?: list<string>
     * }  $validated
     */
    public function update(User $actor, User $user, array $validated): User
    {
        $roles = array_values($validated['roles'] ?? []);

        $this->assertCanChangeRoles($actor, $user, $roles);

        return DB::transaction(function () use ($user, $validated, $roles): User {
            $attributes = [
                'firstName' => $validated['firstName'],
                'middleName' => $validated['middleName'] ?? null,
                'lastName' => $validated['lastName'],
                'email' => $validated['email'],
                'department_id' => $validated['department_id'] ?? null,
            ];

            if (($validated['password'] ?? null) !== null && $validated['password'] !== '') {
                $attributes['password'] = $validated['password'];
            }

            $user->update($attributes);
            $user->syncRoles($roles);

            return $user->fresh(['department', 'roles']) ?? $user;
        });
    }

    public function delete(User $actor, User $user): void
    {
        $this->assertCanDelete($actor, $user);

        $user->delete();
    }

    /**
     * @return array<string, mixed>
     */
    public function serialize(User $user): array
    {
        $user->loadMissing(['department:id,name,slug', 'roles:id,name']);

        return [
            'id' => $user->id,
            'firstName' => $user->firstName,
            'middleName' => $user->middleName,
            'lastName' => $user->lastName,
            'name' => $user->name,
            'email' => $user->email,
            'department' => $user->department === null
                ? null
                : $user->department->only(['id', 'name', 'slug']),
            'roles' => $user->roles
                ->pluck('name')
                ->map(static fn (mixed $name): string => (string) $name)
                ->values()
                ->all(),
        ];
    }

    /**
     * @param  list<string>  $roles
     */
    private function assertCanChangeRoles(User $actor, User $target, array $roles): void
    {
        $hadSuperAdmin = $target->isSuperAdmin();
        $willHaveSuperAdmin = in_array(RoleName::SuperAdmin->value, $roles, true);

        if ($actor->is($target) && $hadSuperAdmin && ! $willHaveSuperAdmin) {
            throw ValidationException::withMessages([
                'roles' => 'You cannot remove your own Super admin role.',
            ]);
        }

        if ($hadSuperAdmin && ! $willHaveSuperAdmin && $this->superAdminCount() <= 1) {
            throw ValidationException::withMessages([
                'roles' => 'The last Super admin cannot be demoted.',
            ]);
        }
    }

    private function assertCanDelete(User $actor, User $target): void
    {
        if ($actor->is($target)) {
            throw ValidationException::withMessages([
                'user' => 'You cannot delete your own account.',
            ]);
        }

        if ($target->isSuperAdmin() && $this->superAdminCount() <= 1) {
            throw ValidationException::withMessages([
                'user' => 'The last Super admin cannot be deleted.',
            ]);
        }
    }

    private function superAdminCount(): int
    {
        return User::role(RoleName::SuperAdmin->value)->count();
    }
}
