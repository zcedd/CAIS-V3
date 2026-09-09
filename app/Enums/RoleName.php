<?php

namespace App\Enums;

enum RoleName: string
{
    case SuperAdmin = 'super-admin';
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

    public function label(): string
    {
        return match ($this) {
            self::SuperAdmin => 'Super admin',
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
