<?php

namespace App\Enums;

enum RoleName: string
{
    case SuperAdmin = 'super-admin';
    case Executive = 'executive';
    case Administrator = 'administrator';
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
            self::Executive,
            self::Administrator,
            self::DepartmentHead,
            self::ReleasingOfficer,
        ];
    }

    /**
     * Roles that review records from every department.
     *
     * @return list<string>
     */
    public static function provinceWideValues(): array
    {
        return [
            self::SuperAdmin->value,
            self::Executive->value,
            self::Administrator->value,
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
            self::Executive => 'Executive',
            self::Administrator => 'Administrator',
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
            self::Executive => 'Executive',
            self::Administrator => 'Administrator',
            self::DepartmentHead => 'Dept head',
            self::ReleasingOfficer => 'Field op',
            default => 'Staff',
        };
    }

    public function summary(): string
    {
        return match ($this) {
            self::SuperAdmin => 'Highest authority in the system. Full access across modules, overrides, and user clearances.',
            self::Executive => 'Reviews program details and approves the program.',
            self::Administrator => 'Endorses programs and distribution batches for executive approval.',
            self::DepartmentHead => 'Formulates assistance programs and submits them for executive approval.',
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
