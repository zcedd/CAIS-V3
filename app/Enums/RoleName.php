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

    public function requiresDepartment(): bool
    {
        return match ($this) {
            self::DepartmentHead, self::ReleasingOfficer => true,
            default => false,
        };
    }

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    public function label(): string
    {
        return match ($this) {
            self::SuperAdmin => 'Super admin',
            self::Governor => 'Governor',
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

    /**
     * @return list<array{value: string, label: string, requires_department: bool}>
     */
    public static function officeOptions(): array
    {
        return array_map(
            static fn (self $role): array => [
                'value' => $role->value,
                'label' => $role->label(),
                'requires_department' => $role->requiresDepartment(),
            ],
            self::officeRoles(),
        );
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
