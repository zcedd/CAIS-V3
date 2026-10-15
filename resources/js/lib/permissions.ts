export const Permission = {
    AssistanceViewAny: 'assistance.viewAny',
    AssistanceView: 'assistance.view',
    AssistanceCreate: 'assistance.create',
    AssistanceUpdate: 'assistance.update',
    AssistanceDelete: 'assistance.delete',
    AssistanceAssign: 'assistance.assign',
    AssistanceClaim: 'assistance.claim',
    AssistanceAdvance: 'assistance.advance',
    ProgramViewAny: 'program.viewAny',
    ProgramView: 'program.view',
    ProgramCreate: 'program.create',
    ProgramUpdate: 'program.update',
    ProgramDelete: 'program.delete',
    ProgramDownloadAssistance: 'program.downloadAssistance',
    ProgramSubmit: 'program.submit',
    ProgramApprove: 'program.approve',
    BeneficiaryViewAny: 'beneficiary.viewAny',
    BeneficiaryView: 'beneficiary.view',
    BeneficiaryCreate: 'beneficiary.create',
    BeneficiaryUpdate: 'beneficiary.update',
    ItemViewAny: 'item.viewAny',
    ItemView: 'item.view',
    ItemCreate: 'item.create',
    ItemUpdate: 'item.update',
    ItemDelete: 'item.delete',
    FundViewAny: 'fund.viewAny',
    FundView: 'fund.view',
    FundCreate: 'fund.create',
    FundUpdate: 'fund.update',
    FundDelete: 'fund.delete',
    WorkflowViewAny: 'workflow.viewAny',
    WorkflowView: 'workflow.view',
    WorkflowCreate: 'workflow.create',
    WorkflowUpdate: 'workflow.update',
    WorkflowDelete: 'workflow.delete',
    WorkflowPublish: 'workflow.publish',
    WorkflowVersion: 'workflow.version',
    WorkflowAssign: 'workflow.assign',
    WorkflowManage: 'workflow.manage',
    WorkflowTaskView: 'workflow.task.view',
    WorkflowTaskAssign: 'workflow.task.assign',
    WorkflowTaskReassign: 'workflow.task.reassign',
    WorkflowTaskOverride: 'workflow.task.override',
    DistributionViewAny: 'distribution.viewAny',
    DistributionView: 'distribution.view',
    DistributionCreate: 'distribution.create',
    DistributionUpdate: 'distribution.update',
    DistributionValidate: 'distribution.validate',
    DistributionSubmit: 'distribution.submit',
    DistributionApprove: 'distribution.approve',
    DistributionReturn: 'distribution.return',
    DistributionReject: 'distribution.reject',
    DistributionModifyBeneficiary: 'distribution.modifyBeneficiary',
    DistributionFinalize: 'distribution.finalize',
    DistributionSchedule: 'distribution.schedule',
    DistributionExecute: 'distribution.execute',
    DistributionReconcile: 'distribution.reconcile',
    DistributionComplete: 'distribution.complete',
    DistributionCancel: 'distribution.cancel',
    DistributionPolicyManage: 'distribution.policy.manage',
    DistributionViewEvidence: 'distribution.viewEvidence',
    DistributionDownloadEvidence: 'distribution.downloadEvidence',
    DistributionIssueReceipt: 'distribution.issueReceipt',
    DistributionReprintReceipt: 'distribution.reprintReceipt',
    DistributionVoidTransaction: 'distribution.voidTransaction',
    DistributionViewAudit: 'distribution.viewAudit',
    DistributionAssignPersonnel: 'distribution.assignPersonnel',
    DistributionWaiveLivePhoto: 'distribution.waiveLivePhoto',
    DepartmentSupervise: 'department.supervise',
} as const;

export type PermissionName = (typeof Permission)[keyof typeof Permission];

export function hasPermission(
    permissions: string[] | undefined,
    permission: string,
    isSuperAdmin = false,
): boolean {
    if (isSuperAdmin) {
        return true;
    }

    return (permissions ?? []).includes(permission);
}

export function hasAnyPermission(
    permissions: string[] | undefined,
    required: string[],
    isSuperAdmin = false,
): boolean {
    if (required.length === 0) {
        return false;
    }

    return required.some((permission) =>
        hasPermission(permissions, permission, isSuperAdmin),
    );
}

export function hasAllPermissions(
    permissions: string[] | undefined,
    required: string[],
    isSuperAdmin = false,
): boolean {
    if (required.length === 0) {
        return false;
    }

    return required.every((permission) =>
        hasPermission(permissions, permission, isSuperAdmin),
    );
}
