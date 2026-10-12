import { router } from '@inertiajs/react';
import { useState } from 'react';
import { toast } from 'sonner';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogClose,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';

export type ProgramDecisionAction = 'endorse' | 'approve' | 'return';

const copy: Record<
    ProgramDecisionAction,
    {
        title: string;
        description: string;
        confirm: string;
        success: string;
        placeholder: string;
        variant: 'default' | 'outline';
    }
> = {
    endorse: {
        title: 'Endorse & forward to Governor?',
        description: 'will be locked for review until the Governor decides.',
        confirm: 'Endorse & forward',
        success: 'Program endorsed and forwarded to the Governor.',
        placeholder: 'Add a remark (optional)',
        variant: 'default',
    },
    approve: {
        title: 'Approve program?',
        description: 'becomes active and can start releasing assistance.',
        confirm: 'Approve',
        success: 'Program approved.',
        placeholder: 'Add a remark (optional)',
        variant: 'default',
    },
    return: {
        title: 'Return to department?',
        description: 'goes back to the department for changes.',
        confirm: 'Return to department',
        success: 'Program returned to the department.',
        placeholder: 'Why are you returning this program?',
        variant: 'outline',
    },
};

export function ProgramDecisionDialog({
    open,
    onOpenChange,
    programName,
    action,
    url,
    onSuccess,
}: {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    programName: string;
    action: ProgramDecisionAction;
    url: string;
    onSuccess?: () => void;
}) {
    const [remark, setRemark] = useState('');
    const [processing, setProcessing] = useState(false);
    const details = copy[action];

    const handleOpenChange = (next: boolean) => {
        if (processing) {
            return;
        }

        if (!next) {
            setRemark('');
        }

        onOpenChange(next);
    };

    const confirm = () => {
        if (url === '' || processing) {
            return;
        }

        const trimmed = remark.trim();

        setProcessing(true);
        router.post(
            url,
            { remark: trimmed === '' ? null : trimmed },
            {
                preserveScroll: true,
                onSuccess: () => {
                    setRemark('');
                    onOpenChange(false);
                    onSuccess?.();
                    toast.success(details.success);
                },
                onError: (errors) => {
                    const message = errors.program ?? errors.remark;

                    toast.error(
                        typeof message === 'string'
                            ? message
                            : 'Could not update this program.',
                    );
                },
                onFinish: () => setProcessing(false),
            },
        );
    };

    return (
        <Dialog open={open} onOpenChange={handleOpenChange}>
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>{details.title}</DialogTitle>
                    <DialogDescription>
                        <span className="font-medium text-foreground">
                            {programName}
                        </span>{' '}
                        {details.description}
                    </DialogDescription>
                </DialogHeader>
                <div className="flex flex-col gap-2">
                    <Label htmlFor="program-decision-remark">Remark</Label>
                    <Textarea
                        id="program-decision-remark"
                        value={remark}
                        maxLength={1000}
                        disabled={processing}
                        placeholder={details.placeholder}
                        onChange={(event) => setRemark(event.target.value)}
                    />
                </div>
                <DialogFooter>
                    <DialogClose asChild>
                        <Button variant="outline" disabled={processing}>
                            Cancel
                        </Button>
                    </DialogClose>
                    <Button
                        type="button"
                        variant={details.variant}
                        disabled={processing || url === ''}
                        onClick={confirm}
                    >
                        {details.confirm}
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}
