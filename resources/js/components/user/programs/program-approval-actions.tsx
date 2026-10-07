import { Form } from '@inertiajs/react';
import { Button } from '@/components/ui/button';
import { useCan } from '@/hooks/use-can';
import { Permission } from '@/lib/permissions';
import {
    endorse as endorseProgram,
    revise as reviseProgram,
    submit as submitProgram,
} from '@/routes/user/programs';

type ProgramApprovalActionsProps = {
    departmentSlug: string;
    programId: number;
    approvalStatus?: string | null;
    approvalLabel?: string | null;
    returnComment?: string | null;
};

export function ProgramApprovalActions({
    departmentSlug,
    programId,
    approvalStatus,
    approvalLabel,
    returnComment,
}: ProgramApprovalActionsProps) {
    const can = useCan();
    const routeArgs = { department: departmentSlug, program: programId };
    const canSubmit =
        can(Permission.ProgramSubmit) &&
        (approvalStatus === 'draft' || approvalStatus === 'returned');
    const canEndorse =
        can(Permission.ProgramEndorse) && approvalStatus === 'proposed';

    if (!approvalLabel && !canSubmit && !canEndorse && !returnComment) {
        return null;
    }

    return (
        <div className="flex flex-col gap-3 rounded-lg border bg-card p-4">
            <div className="flex flex-wrap items-center justify-between gap-3">
                <div>
                    <p className="text-sm text-muted-foreground">Approval</p>
                    <p className="font-medium">{approvalLabel ?? 'Not set'}</p>
                </div>
                <div className="flex flex-wrap gap-2">
                    {canSubmit ? (
                        <Form {...submitProgram.form.post(routeArgs)}>
                            <Button type="submit">Submit for review</Button>
                        </Form>
                    ) : null}
                    {canEndorse ? (
                        <>
                            <Form {...endorseProgram.form.post(routeArgs)}>
                                <Button type="submit">Endorse</Button>
                            </Form>
                            <Form {...reviseProgram.form.post(routeArgs)}>
                                <Button type="submit" variant="outline">
                                    Revise
                                </Button>
                            </Form>
                        </>
                    ) : null}
                </div>
            </div>
            {returnComment ? (
                <p className="text-sm text-muted-foreground">
                    Returned: {returnComment}
                </p>
            ) : null}
        </div>
    );
}
