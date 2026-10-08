export const Permission = {
    AssistanceViewAny: 'assistance.viewAny',
    ProgramViewAny: 'program.viewAny',
    ProgramApprove: 'program.approve',
    ProgramSubmit: 'program.submit',
    BeneficiaryViewAny: 'beneficiary.viewAny',
    ItemViewAny: 'item.viewAny',
    FundViewAny: 'fund.viewAny',
    WorkflowViewAny: 'workflow.viewAny',
    WorkflowCreate: 'workflow.create',
    WorkflowUpdate: 'workflow.update',
    WorkflowPublish: 'workflow.publish',
    WorkflowVersion: 'workflow.version',
    WorkflowAssign: 'workflow.assign',
    WorkflowManage: 'workflow.manage',
} as const;

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
