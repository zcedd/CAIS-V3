<?php

namespace App\Enums;

enum WorkflowAssignmentType: string
{
    case None = 'none';
    case User = 'user';
    case Role = 'role';
    case DepartmentRole = 'department_role';

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
            self::None => 'Claim pool',
            self::User => 'User',
            self::Role => 'Role',
            self::DepartmentRole => 'Department role',
        };
    }
}
