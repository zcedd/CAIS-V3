import { Send } from 'lucide-react';
import { useState } from 'react';
import { Button } from '@/components/ui/button';
import { ProgramDecisionDialog } from '@/components/user/programs/program-decision-dialog';
import { store as submitProgramApproval } from '@/routes/user/programs/approval';

export function ProgramApprovalSubmit({
    departmentSlug,
    programId,
    programName,
    canSubmit = false,
}: {
    departmentSlug: string;
    programId: number;
    programName: string;
    canSubmit?: boolean;
}) {
    const [open, setOpen] = useState(false);

    if (!canSubmit) {
        return null;
    }

    return (
        <>
            <Button type="button" onClick={() => setOpen(true)}>
                <Send data-icon="inline-start" />
                Submit for recommendation
            </Button>
            <ProgramDecisionDialog
                open={open}
                onOpenChange={setOpen}
                programName={programName}
                action="submit"
                url={submitProgramApproval.url({
                    department: departmentSlug,
                    program: programId,
                })}
            />
        </>
    );
}
