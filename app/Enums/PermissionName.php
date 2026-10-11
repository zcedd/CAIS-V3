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

    case DisbursementViewAny = 'disbursement.viewAny';
    case DisbursementView = 'disbursement.view';
    case DisbursementCreate = 'disbursement.create';
    case DisbursementUpdate = 'disbursement.update';
    case DisbursementValidate = 'disbursement.validate';
    case DisbursementSubmit = 'disbursement.submit';
    case DisbursementApprove = 'disbursement.approve';
    case DisbursementReturn = 'disbursement.return';
    case DisbursementReject = 'disbursement.reject';
    case DisbursementModifyBeneficiary = 'disbursement.modifyBeneficiary';
    case DisbursementFinalize = 'disbursement.finalize';
    case DisbursementSchedule = 'disbursement.schedule';
    case DisbursementExecute = 'disbursement.execute';
    case DisbursementReconcile = 'disbursement.reconcile';
    case DisbursementComplete = 'disbursement.complete';
    case DisbursementCancel = 'disbursement.cancel';
    case DisbursementPolicyManage = 'disbursement.policy.manage';
    case DisbursementViewEvidence = 'disbursement.viewEvidence';
    case DisbursementDownloadEvidence = 'disbursement.downloadEvidence';
    case DisbursementIssueReceipt = 'disbursement.issueReceipt';
    case DisbursementReprintReceipt = 'disbursement.reprintReceipt';
    case DisbursementVoidTransaction = 'disbursement.voidTransaction';
    case DisbursementViewAudit = 'disbursement.viewAudit';
    case DisbursementAssignPersonnel = 'disbursement.assignPersonnel';
    case DisbursementWaiveLivePhoto = 'disbursement.waiveLivePhoto';

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
                self::disbursementStaffPermissions(),
            ),
            RoleName::Beneficiary => self::forResource('beneficiary'),
            RoleName::Item => self::forResource('item'),
            RoleName::Fund => self::forResource('fund'),
            RoleName::Workflow => self::departmentWorkflowPermissions(),
            RoleName::Supervisor => [self::DepartmentSupervise],
            RoleName::Head => self::cases(),
            RoleName::SuperAdmin => self::cases(),
            RoleName::Executive => self::executivePermissions(),
            RoleName::DepartmentHead => self::departmentHeadPermissions(),
            RoleName::ReleasingOfficer => self::releasingOfficerPermissions(),
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
            self::DisbursementViewAny => 'View disbursement batches',
            self::DisbursementView => 'View a disbursement batch',
            self::DisbursementCreate => 'Create disbursement batches',
            self::DisbursementUpdate => 'Update disbursement batches',
            self::DisbursementValidate => 'Validate disbursement batches',
            self::DisbursementSubmit => 'Submit disbursement batches',
            self::DisbursementApprove => 'Approve a disbursement batch',
            self::DisbursementReturn => 'Return a disbursement batch',
            self::DisbursementReject => 'Reject a disbursement batch',
            self::DisbursementModifyBeneficiary => 'Modify a disbursement beneficiary amount',
            self::DisbursementFinalize => 'Finalize disbursement approval',
            self::DisbursementSchedule => 'Schedule a disbursement',
            self::DisbursementExecute => 'Record disbursement releases',
            self::DisbursementReconcile => 'Reconcile a disbursement',
            self::DisbursementComplete => 'Complete a disbursement',
            self::DisbursementCancel => 'Cancel a disbursement',
            self::DisbursementPolicyManage => 'Manage disbursement approval policies',
            self::DisbursementViewEvidence => 'View disbursement evidence',
            self::DisbursementDownloadEvidence => 'Download disbursement evidence',
            self::DisbursementIssueReceipt => 'Issue a disbursement acknowledgment receipt',
            self::DisbursementReprintReceipt => 'Reprint a disbursement receipt',
            self::DisbursementVoidTransaction => 'Void a disbursement release',
            self::DisbursementViewAudit => 'View the disbursement audit log',
            self::DisbursementAssignPersonnel => 'Assign disbursement personnel',
            self::DisbursementWaiveLivePhoto => 'Waive live photo evidence',
            self::DepartmentSupervise => 'Supervise the department',
        };
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
            self::DisbursementViewAny,
            self::DisbursementView,
            self::DisbursementViewAudit,
            self::DisbursementApprove,
            self::DisbursementReturn,
            self::DisbursementReject,
            self::DisbursementFinalize,
            self::DisbursementModifyBeneficiary,
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
            self::DisbursementViewAny,
            self::DisbursementView,
            self::DisbursementCreate,
            self::DisbursementUpdate,
            self::DisbursementValidate,
            self::DisbursementSubmit,
            self::DisbursementApprove,
            self::DisbursementReturn,
            self::DisbursementReject,
            self::DisbursementFinalize,
            self::DisbursementSchedule,
            self::DisbursementCancel,
            self::DisbursementReconcile,
            self::DisbursementViewEvidence,
            self::DisbursementDownloadEvidence,
            self::DisbursementViewAudit,
            self::DisbursementAssignPersonnel,
            self::DisbursementVoidTransaction,
            self::DisbursementReprintReceipt,
        ];
    }

    /**
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
            self::DisbursementViewAny,
            self::DisbursementView,
            self::DisbursementExecute,
            self::DisbursementReconcile,
            self::DisbursementComplete,
            self::DisbursementViewEvidence,
            self::DisbursementDownloadEvidence,
            self::DisbursementIssueReceipt,
            self::DisbursementReprintReceipt,
        ];
    }

    /**
     * Department staff who prepare a disbursement, without approval authority.
     *
     * @return list<self>
     */
    public static function disbursementStaffPermissions(): array
    {
        return [
            self::DisbursementViewAny,
            self::DisbursementView,
            self::DisbursementCreate,
            self::DisbursementUpdate,
            self::DisbursementValidate,
            self::DisbursementSubmit,
            self::DisbursementSchedule,
            self::DisbursementCancel,
            self::DisbursementAssignPersonnel,
            self::DisbursementViewAudit,
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
