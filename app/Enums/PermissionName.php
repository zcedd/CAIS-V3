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

    case DistributionViewAny = 'distribution.viewAny';
    case DistributionView = 'distribution.view';
    case DistributionCreate = 'distribution.create';
    case DistributionUpdate = 'distribution.update';
    case DistributionValidate = 'distribution.validate';
    case DistributionSubmit = 'distribution.submit';
    case DistributionApprove = 'distribution.approve';
    case DistributionReturn = 'distribution.return';
    case DistributionReject = 'distribution.reject';
    case DistributionModifyBeneficiary = 'distribution.modifyBeneficiary';
    case DistributionFinalize = 'distribution.finalize';
    case DistributionSchedule = 'distribution.schedule';
    case DistributionExecute = 'distribution.execute';
    case DistributionReconcile = 'distribution.reconcile';
    case DistributionComplete = 'distribution.complete';
    case DistributionCancel = 'distribution.cancel';
    case DistributionPolicyManage = 'distribution.policy.manage';
    case DistributionViewEvidence = 'distribution.viewEvidence';
    case DistributionDownloadEvidence = 'distribution.downloadEvidence';
    case DistributionIssueReceipt = 'distribution.issueReceipt';
    case DistributionReprintReceipt = 'distribution.reprintReceipt';
    case DistributionVoidTransaction = 'distribution.voidTransaction';
    case DistributionViewAudit = 'distribution.viewAudit';
    case DistributionAssignPersonnel = 'distribution.assignPersonnel';
    case DistributionWaiveLivePhoto = 'distribution.waiveLivePhoto';

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
            RoleName::Program => array_merge(
                self::programResourcePermissions(),
                self::distributionStaffPermissions(),
            ),
            RoleName::Beneficiary => self::forResource('beneficiary'),
            RoleName::Item => self::forResource('item'),
            RoleName::Fund => self::forResource('fund'),
            RoleName::Workflow => self::departmentWorkflowPermissions(),
            RoleName::Supervisor => [self::DepartmentSupervise],
            RoleName::Head => self::cases(),
            RoleName::SuperAdmin => self::cases(),
            RoleName::Executive => self::executivePermissions(),
            RoleName::Administrator => self::administratorPermissions(),
            RoleName::DepartmentHead => self::departmentHeadPermissions(),
            RoleName::Encoder => self::encoderPermissions(),
            RoleName::Verifier => self::verifierPermissions(),
            RoleName::ReleasingOfficer => self::releasingOfficerPermissions(),
            RoleName::InventoryOfficer => self::inventoryOfficerPermissions(),
        };
    }

    public function label(): string
    {
        return match ($this) {
            self::AssistanceViewAny => 'View assistance queue',
            self::AssistanceView => 'View an assistance request',
            self::AssistanceCreate => 'Encode assistance',
            self::AssistanceUpdate => 'Update assistance',
            self::AssistanceDelete => 'Delete assistance',
            self::AssistanceAssign => 'Assign assistance',
            self::AssistanceClaim => 'Claim assistance',
            self::AssistanceAdvance => 'Advance assistance status',
            self::ProgramViewAny => 'View programs',
            self::ProgramView => 'View a program',
            self::ProgramCreate => 'Create programs',
            self::ProgramUpdate => 'Update programs',
            self::ProgramDelete => 'Delete programs',
            self::ProgramDownloadAssistance => 'Download assistance and receipts',
            self::ProgramSubmit => 'Submit a program for executive approval',
            self::ProgramEndorse => 'Endorse or return a program',
            self::ProgramApprove => 'Approve or return a program',
            self::BeneficiaryViewAny => 'View beneficiaries',
            self::BeneficiaryView => 'View a beneficiary',
            self::BeneficiaryCreate => 'Enroll beneficiaries',
            self::BeneficiaryUpdate => 'Update beneficiaries',
            self::ItemViewAny => 'View items',
            self::ItemView => 'View an item',
            self::ItemCreate => 'Create items',
            self::ItemUpdate => 'Update items',
            self::ItemDelete => 'Delete items',
            self::FundViewAny => 'View funds',
            self::FundView => 'View a fund',
            self::FundCreate => 'Create funds',
            self::FundUpdate => 'Update funds',
            self::FundDelete => 'Delete funds',
            self::WorkflowViewAny => 'View workflows',
            self::WorkflowView => 'View a workflow',
            self::WorkflowCreate => 'Create workflows',
            self::WorkflowUpdate => 'Update workflows',
            self::WorkflowDelete => 'Delete workflows',
            self::WorkflowPublish => 'Publish workflows',
            self::WorkflowVersion => 'Version workflows',
            self::WorkflowAssign => 'Assign workflows',
            self::WorkflowManage => 'Manage workflows',
            self::WorkflowTaskView => 'View workflow tasks',
            self::WorkflowTaskAssign => 'Assign workflow tasks',
            self::WorkflowTaskReassign => 'Reassign workflow tasks',
            self::WorkflowTaskOverride => 'Override workflow tasks',
            self::DistributionViewAny => 'View distribution batches',
            self::DistributionView => 'View a distribution batch',
            self::DistributionCreate => 'Create distribution batches',
            self::DistributionUpdate => 'Update distribution batches',
            self::DistributionValidate => 'Validate distribution batches',
            self::DistributionSubmit => 'Submit distribution batches',
            self::DistributionApprove => 'Approve a distribution batch',
            self::DistributionReturn => 'Return a distribution batch',
            self::DistributionReject => 'Reject a distribution batch',
            self::DistributionModifyBeneficiary => 'Modify a distribution beneficiary amount',
            self::DistributionFinalize => 'Finalize distribution approval',
            self::DistributionSchedule => 'Schedule a distribution',
            self::DistributionExecute => 'Record distribution releases',
            self::DistributionReconcile => 'Reconcile a distribution',
            self::DistributionComplete => 'Complete a distribution',
            self::DistributionCancel => 'Cancel a distribution',
            self::DistributionPolicyManage => 'Manage distribution approval policies',
            self::DistributionViewEvidence => 'View distribution evidence',
            self::DistributionDownloadEvidence => 'Download distribution evidence',
            self::DistributionIssueReceipt => 'Issue a distribution acknowledgment receipt',
            self::DistributionReprintReceipt => 'Reprint a distribution receipt',
            self::DistributionVoidTransaction => 'Void a distribution release',
            self::DistributionViewAudit => 'View the distribution audit log',
            self::DistributionAssignPersonnel => 'Assign distribution personnel',
            self::DistributionWaiveLivePhoto => 'Waive live photo evidence',
            self::DepartmentSupervise => 'Supervise the department',
        };
    }

    /**
     * @return list<self>
     */
    public static function administratorPermissions(): array
    {
        return [
            self::ProgramViewAny,
            self::ProgramView,
            self::ProgramEndorse,
            self::DistributionViewAny,
            self::DistributionView,
            self::DistributionApprove,
            self::DistributionReturn,
            self::DistributionReject,
        ];
    }

    /**
     * @return list<self>
     */
    public static function executivePermissions(): array
    {
        return [
            self::ProgramViewAny,
            self::ProgramView,
            self::ProgramApprove,
            self::BeneficiaryViewAny,
            self::BeneficiaryView,
            self::AssistanceViewAny,
            self::AssistanceView,
            self::FundViewAny,
            self::FundView,
            self::DistributionViewAny,
            self::DistributionView,
            self::DistributionViewAudit,
            self::DistributionApprove,
            self::DistributionReturn,
            self::DistributionReject,
            self::DistributionFinalize,
            self::DistributionModifyBeneficiary,
        ];
    }

    /**
     * @return list<self>
     */
    public static function departmentHeadPermissions(): array
    {
        return [
            self::ProgramViewAny,
            self::ProgramView,
            self::ProgramCreate,
            self::ProgramUpdate,
            self::ProgramDelete,
            self::ProgramDownloadAssistance,
            self::ProgramSubmit,
            self::AssistanceViewAny,
            self::AssistanceView,
            self::AssistanceUpdate,
            self::AssistanceAssign,
            self::BeneficiaryViewAny,
            self::BeneficiaryView,
            self::BeneficiaryUpdate,
            self::ItemViewAny,
            self::ItemView,
            self::ItemCreate,
            self::ItemUpdate,
            self::FundViewAny,
            self::FundView,
            self::FundCreate,
            self::FundUpdate,
            self::FundDelete,
            self::DepartmentSupervise,
            self::WorkflowViewAny,
            self::WorkflowView,
            self::WorkflowTaskView,
            self::DistributionViewAny,
            self::DistributionView,
            self::DistributionCreate,
            self::DistributionUpdate,
            self::DistributionValidate,
            self::DistributionSubmit,
            self::DistributionApprove,
            self::DistributionReturn,
            self::DistributionReject,
            self::DistributionFinalize,
            self::DistributionSchedule,
            self::DistributionCancel,
            self::DistributionReconcile,
            self::DistributionViewEvidence,
            self::DistributionDownloadEvidence,
            self::DistributionViewAudit,
            self::DistributionAssignPersonnel,
            self::DistributionVoidTransaction,
            self::DistributionReprintReceipt,
        ];
    }

    /**
     * Encodes assistance onto a program, including the beneficiary the request is for.
     *
     * @return list<self>
     */
    public static function encoderPermissions(): array
    {
        return [
            self::ProgramViewAny,
            self::ProgramView,
            self::AssistanceViewAny,
            self::AssistanceView,
            self::AssistanceCreate,
            self::BeneficiaryViewAny,
            self::BeneficiaryView,
            self::BeneficiaryCreate,
        ];
    }

    /**
     * Reviews a request, updates its record, and advances its status through verification.
     *
     * @return list<self>
     */
    public static function verifierPermissions(): array
    {
        return [
            self::ProgramViewAny,
            self::ProgramView,
            self::AssistanceViewAny,
            self::AssistanceView,
            self::AssistanceUpdate,
            self::AssistanceAdvance,
            self::BeneficiaryViewAny,
            self::BeneficiaryView,
        ];
    }

    /**
     * Operates a distribution without program or batch approval authority.
     *
     * @return list<self>
     */
    public static function releasingOfficerPermissions(): array
    {
        return [
            self::ProgramViewAny,
            self::ProgramView,
            self::ProgramDownloadAssistance,
            self::AssistanceViewAny,
            self::AssistanceView,
            self::AssistanceCreate,
            self::AssistanceUpdate,
            self::AssistanceClaim,
            self::AssistanceAdvance,
            self::BeneficiaryViewAny,
            self::BeneficiaryView,
            self::BeneficiaryCreate,
            self::BeneficiaryUpdate,
            self::ItemViewAny,
            self::ItemView,
            self::DistributionViewAny,
            self::DistributionView,
            self::DistributionCreate,
            self::DistributionUpdate,
            self::DistributionValidate,
            self::DistributionSubmit,
            self::DistributionSchedule,
            self::DistributionExecute,
            self::DistributionReconcile,
            self::DistributionComplete,
            self::DistributionCancel,
            self::DistributionViewEvidence,
            self::DistributionDownloadEvidence,
            self::DistributionIssueReceipt,
            self::DistributionReprintReceipt,
            self::DistributionVoidTransaction,
            self::DistributionViewAudit,
            self::DistributionAssignPersonnel,
        ];
    }

    /**
     * @return list<self>
     */
    public static function inventoryOfficerPermissions(): array
    {
        return self::forResource('item');
    }

    /**
     * Department staff who prepare a distribution, without approval authority.
     *
     * @return list<self>
     */
    public static function distributionStaffPermissions(): array
    {
        return [
            self::DistributionViewAny,
            self::DistributionView,
            self::DistributionCreate,
            self::DistributionUpdate,
            self::DistributionValidate,
            self::DistributionSubmit,
            self::DistributionSchedule,
            self::DistributionCancel,
            self::DistributionAssignPersonnel,
            self::DistributionViewAudit,
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
        return array_values(array_filter(
            self::cases(),
            static fn (self $permission): bool => str_starts_with($permission->value, $resource.'.'),
        ));
    }

    /**
     * Module access for program staff. Office approval stays on the office roles.
     *
     * @return list<self>
     */
    public static function programResourcePermissions(): array
    {
        return array_values(array_filter(
            self::forResource('program'),
            static fn (self $permission): bool => ! in_array($permission, [
                self::ProgramSubmit,
                self::ProgramApprove,
            ], true),
        ));
    }
}
