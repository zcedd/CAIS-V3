import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { store as submitProgramApproval } from '@/routes/user/programs/approval';
import { Form } from '@inertiajs/react';
import { Send } from 'lucide-react';

export function ProgramApprovalSubmit({
    departmentSlug,
    programId,
    canSubmit = false,
}: {
    departmentSlug: string;
    programId: number;
    canSubmit?: boolean;
}) {
    if (!canSubmit) {
        return null;
    }

    return (
        <Form
            action={submitProgramApproval.url({
                department: departmentSlug,
                program: programId,
            })}
            method="post"
        >
            {({ processing, errors }) => (
                <div className="flex flex-col items-start gap-1">
                    <Button type="submit" size="sm" disabled={processing}>
                        <Send className="size-4" />
                        Submit for executive approval
                    </Button>
                    <InputError message={errors.program} />
                </div>
            )}
        </Form>
    );
}
