<?php

namespace App\Enums;

enum PermissionName: string
{
    case AssistanceViewAny = 'assistance.viewAny';
    case AssistanceView = 'assistance.view';
    case AssistanceCreate = 'assistance.create';
    case AssistanceUpdate = 'assistance.update';
    case AssistanceDelete = 'assistance.delete';
    case AssistanceAssign = 'assistance.assign';
    case AssistanceClaim = 'assistance.claim';
    case AssistanceAdvance = 'assistance.advance';

    case ProgramViewAny = 'program.viewAny';
    case ProgramView = 'program.view';
    case ProgramCreate = 'program.create';
    case ProgramUpdate = 'program.update';
    case ProgramDelete = 'program.delete';
    case ProgramDownloadAssistance = 'program.downloadAssistance';

    case BeneficiaryViewAny = 'beneficiary.viewAny';
    case BeneficiaryView = 'beneficiary.view';
    case BeneficiaryCreate = 'beneficiary.create';
    case BeneficiaryUpdate = 'beneficiary.update';

    case ItemViewAny = 'item.viewAny';
    case ItemView = 'item.view';
    case ItemCreate = 'item.create';
    case ItemUpdate = 'item.update';
    case ItemDelete = 'item.delete';

    case FundViewAny = 'fund.viewAny';
    case FundView = 'fund.view';
    case FundCreate = 'fund.create';
    case FundUpdate = 'fund.update';
    case FundDelete = 'fund.delete';

    case WorkflowViewAny = 'workflow.viewAny';
    case WorkflowView = 'workflow.view';
    case WorkflowCreate = 'workflow.create';
    case WorkflowUpdate = 'workflow.update';
    case WorkflowDelete = 'workflow.delete';

    case DepartmentSupervise = 'department.supervise';

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
    public static function forRole(RoleName $role): array
    {
        return match ($role) {
            RoleName::Assistance => self::forResource('assistance'),
            RoleName::Program => self::forResource('program'),
            RoleName::Beneficiary => self::forResource('beneficiary'),
            RoleName::Item => self::forResource('item'),
            RoleName::Fund => self::forResource('fund'),
            RoleName::Workflow => self::forResource('workflow'),
            RoleName::Supervisor => [self::DepartmentSupervise],
            RoleName::Head => self::cases(),
            RoleName::SuperAdmin => [],
        };
    }

    /**
     * @return list<self>
     */
    public static function forResource(string $resource): array
    {
        return array_values(array_filter(
            self::cases(),
            static fn (self $permission): bool => str_starts_with($permission->value, $resource.'.'),
        ));
    }
}
