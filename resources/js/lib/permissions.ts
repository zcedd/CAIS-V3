export const Permission = {
    AssistanceViewAny: 'assistance.viewAny',
    ProgramViewAny: 'program.viewAny',
    ProgramSubmit: 'program.submit',
    ProgramEndorse: 'program.endorse',
    ProgramApprove: 'program.approve',
    ProgramReturn: 'program.return',
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
