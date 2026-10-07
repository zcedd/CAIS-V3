<?php

namespace App\Services\Auth;

use App\Enums\RoleName;
use App\Models\Department;
use App\Models\User;
use App\Services\User\ProgramApprovalService;
use App\Support\OfficeCatalog;
use Illuminate\Http\RedirectResponse;
use Illuminate\Validation\ValidationException;

class OfficePortal
{
    public const SESSION_OFFICE = 'active_office';

    public const SESSION_DEPARTMENT = 'active_office_department_id';

    public function __construct(private ProgramApprovalService $approvals) {}

    /**
     * @return list<RoleName>
     */
    public function allowedOffices(User $user): array
    {
        if ($user->isSuperAdmin()) {
            return RoleName::officeRoles();
        }

        return array_values(array_filter(
            RoleName::officeRoles(),
            fn (RoleName $role): bool => $user->hasRole($role),
        ));
    }

    public function activeOffice(User $user): ?RoleName
    {
        $value = session(self::SESSION_OFFICE);

        if (! is_string($value)) {
            return null;
        }

        $office = RoleName::tryFrom($value);

        if ($office === null || ! in_array($office, $this->allowedOffices($user), true)) {
            return null;
        }

        return $office;
    }

    public function enter(User $user, RoleName $office, ?Department $department): RedirectResponse
    {
        if (! in_array($office, $this->allowedOffices($user), true)) {
            abort(403);
        }

        $department ??= $user->department;

        if ($office->requiresDepartment() && $department === null) {
            throw ValidationException::withMessages([
                'department_id' => 'Choose a department for this office.',
            ]);
        }

        session([
            self::SESSION_OFFICE => $office->value,
            self::SESSION_DEPARTMENT => $department?->id,
        ]);

        return $this->homeRedirect($user, $office, $department)
            ?? redirect()->route('dashboard');
    }

    public function homeRedirect(User $user, RoleName $office, ?Department $department = null): ?RedirectResponse
    {
        $department ??= $user->department ?? $this->sessionDepartment();

        if ($office->requiresDepartment() && $department === null) {
            return null;
        }

        return match ($office) {
            RoleName::SuperAdmin => redirect()->route('admin.users.index'),
            RoleName::Governor => redirect()->route('governor.programs.index'),
            RoleName::DepartmentHead => redirect()->route('user.dashboard.index', $department),
            RoleName::ReleasingOfficer => redirect()->route('user.queue.index', $department),
            default => redirect()->route('dashboard'),
        };
    }

    private function sessionDepartment(): ?Department
    {
        $departmentId = session(self::SESSION_DEPARTMENT);

        if (! is_numeric($departmentId)) {
            return null;
        }

        return Department::query()->find((int) $departmentId);
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function cards(User $user): array
    {
        $departmentId = $user->isSuperAdmin() ? null : $user->department_id;

        return array_map(function (RoleName $office) use ($departmentId): array {
            $pending = match ($office) {
                RoleName::DepartmentHead => $this->approvals->proposedCount($departmentId),
                RoleName::Governor => $this->approvals->awaitingGovernorCount(),
                default => null,
            };

            return OfficeCatalog::card($office, $pending);
        }, $this->allowedOffices($user));
    }
}
