<?php

namespace App\Enums;

enum RoleName: string
{
    case SuperAdmin = 'super-admin';
    case Governor = 'governor';
    case DepartmentHead = 'department-head';
    case ReleasingOfficer = 'releasing-officer';
    case Assistance = 'assistance';
    case Program = 'program';
    case Beneficiary = 'beneficiary';
    case Item = 'item';
    case Fund = 'fund';
    case Workflow = 'workflow';
    case Supervisor = 'supervisor';
    case Head = 'head';

    /**
     * @return list<self>
     */
    public static function resourceRoles(): array
    {
        return [
            self::Assistance,
            self::Program,
            self::Beneficiary,
            self::Item,
            self::Fund,
            self::Workflow,
        ];
    }

    /**
     * @return list<string>
     */
    public static function resourceRoleValues(): array
    {
        return array_column(self::resourceRoles(), 'value');
    }

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    /**
     * @return list<self>
     */
    public static function officeRoles(): array
    {
        return [
            self::SuperAdmin,
            self::Governor,
            self::DepartmentHead,
            self::ReleasingOfficer,
        ];
    }

    /**
     * @return list<string>
     */
    public static function officeRoleValues(): array
    {
        return array_column(self::officeRoles(), 'value');
    }

    public function label(): string
    {
        return match ($this) {
            self::SuperAdmin => 'Super admin',
            self::Governor => 'Executive',
            self::DepartmentHead => 'Department head',
            self::ReleasingOfficer => 'Releasing officer',
            self::Assistance => 'Assistance',
            self::Program => 'Program',
            self::Beneficiary => 'Beneficiary',
            self::Item => 'Item',
            self::Fund => 'Fund',
            self::Workflow => 'Workflow',
            self::Supervisor => 'Supervisor',
            self::Head => 'Head',
        };
    }

    public function tier(): string
    {
        return match ($this) {
            self::SuperAdmin => 'Supreme',
            self::Governor => 'Executive',
            self::DepartmentHead => 'Dept head',
            self::ReleasingOfficer => 'Field op',
            default => 'Staff',
        };
    }

    public function summary(): string
    {
        return match ($this) {
            self::SuperAdmin => 'Highest authority in the system. Full access across modules, overrides, and user clearances.',
            self::Governor => 'Reviews endorsed programs and their verified beneficiaries, then approves them for release.',
            self::DepartmentHead => 'Formulates assistance programs and endorses verified beneficiaries for executive approval.',
            self::ReleasingOfficer => 'Enrolls beneficiaries and releases assistance after the executive approves it.',
            default => $this->label(),
        };
    }

    /**
     * @return list<array{value: string, label: string}>
     */
    public static function options(): array
    {
        return array_map(
            static fn (self $role): array => [
                'value' => $role->value,
                'label' => $role->label(),
            ],
            self::cases(),
        );
    }
}
