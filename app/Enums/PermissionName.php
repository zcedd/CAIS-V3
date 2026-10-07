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
    case ProgramSubmit = 'program.submit';
    case ProgramEndorse = 'program.endorse';
    case ProgramApprove = 'program.approve';
    case ProgramReturn = 'program.return';

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
    case WorkflowPublish = 'workflow.publish';
    case WorkflowVersion = 'workflow.version';
    case WorkflowAssign = 'workflow.assign';
    case WorkflowManage = 'workflow.manage';
    case WorkflowTaskView = 'workflow.task.view';
    case WorkflowTaskAssign = 'workflow.task.assign';
    case WorkflowTaskReassign = 'workflow.task.reassign';
    case WorkflowTaskOverride = 'workflow.task.override';

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
            RoleName::Workflow => self::departmentWorkflowPermissions(),
            RoleName::Supervisor => [self::DepartmentSupervise],
            RoleName::Head, RoleName::DepartmentHead => self::departmentHeadPermissions(),
            RoleName::ReleasingOfficer => self::releasingOfficerPermissions(),
            RoleName::Governor => self::governorPermissions(),
            RoleName::SuperAdmin => self::workflowManagement(),
        };
    }

    /**
     * @return list<self>
     */
    public static function departmentHeadPermissions(): array
    {
        return [
            ...self::forResource('program'),
            self::ProgramSubmit,
            self::ProgramEndorse,
            ...self::forResource('fund'),
            ...self::forResource('item'),
            self::BeneficiaryViewAny,
            self::BeneficiaryView,
            self::AssistanceViewAny,
            self::AssistanceView,
        ];
    }

    /**
     * @return list<self>
     */
    public static function releasingOfficerPermissions(): array
    {
        return [
            self::BeneficiaryViewAny,
            self::BeneficiaryView,
            self::BeneficiaryCreate,
            self::BeneficiaryUpdate,
            self::AssistanceViewAny,
            self::AssistanceView,
            self::AssistanceCreate,
            self::AssistanceUpdate,
            self::AssistanceClaim,
            self::AssistanceAdvance,
            self::ProgramViewAny,
            self::ProgramView,
            self::ItemViewAny,
            self::ItemView,
        ];
    }

    /**
     * @return list<self>
     */
    public static function governorPermissions(): array
    {
        return [
            self::ProgramViewAny,
            self::ProgramView,
            self::ProgramApprove,
            self::ProgramReturn,
            self::FundViewAny,
            self::FundView,
            self::AssistanceViewAny,
            self::AssistanceView,
            self::BeneficiaryViewAny,
            self::BeneficiaryView,
        ];
    }

    /**
     * @return list<self>
     */
    public static function workflowManagement(): array
    {
        return [
            self::WorkflowViewAny,
            self::WorkflowView,
            self::WorkflowCreate,
            self::WorkflowUpdate,
            self::WorkflowDelete,
            self::WorkflowPublish,
            self::WorkflowVersion,
            self::WorkflowAssign,
            self::WorkflowManage,
            self::WorkflowTaskView,
            self::WorkflowTaskAssign,
            self::WorkflowTaskReassign,
            self::WorkflowTaskOverride,
        ];
    }

    /**
     * @return list<self>
     */
    public static function departmentWorkflowPermissions(): array
    {
        return [
            self::WorkflowViewAny,
            self::WorkflowView,
            self::WorkflowCreate,
            self::WorkflowUpdate,
            self::WorkflowDelete,
            self::WorkflowVersion,
            self::WorkflowTaskView,
            self::WorkflowTaskAssign,
            self::WorkflowTaskReassign,
        ];
    }

    /**
     * @return list<self>
     */
    public static function forResource(string $resource): array
    {
        $approval = [
            self::ProgramSubmit,
            self::ProgramEndorse,
            self::ProgramApprove,
            self::ProgramReturn,
        ];

        return array_values(array_filter(
            self::cases(),
            static fn (self $permission): bool => str_starts_with($permission->value, $resource.'.')
                && ! in_array($permission, $approval, true),
        ));
    }
}
