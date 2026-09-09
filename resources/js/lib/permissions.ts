export const Permission = {
    AssistanceViewAny: 'assistance.viewAny',
    ProgramViewAny: 'program.viewAny',
    BeneficiaryViewAny: 'beneficiary.viewAny',
    ItemViewAny: 'item.viewAny',
    FundViewAny: 'fund.viewAny',
    WorkflowViewAny: 'workflow.viewAny',
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
