import { useState } from 'react';
import type { ReactNode } from 'react';
import { Badge } from '@/components/ui/badge';
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
import { Spinner } from '@/components/ui/spinner';

export type DistributionStatusVariant =
    | 'default'
    | 'secondary'
    | 'destructive'
    | 'outline';

export type VisitCallbacks = {
    onStart?: () => void;
    onSuccess?: () => void;
    onFinish?: () => void;
};

export function distributionStatusVariant(
    status: string,
): DistributionStatusVariant {
    if (['approved', 'finalized', 'completed', 'scheduled'].includes(status)) {
        return 'default';
    }

    if (['rejected', 'excluded', 'cancelled', 'voided'].includes(status)) {
        return 'destructive';
    }

    if (['returned', 'draft'].includes(status)) {
        return 'secondary';
    }

    return 'outline';
}

type StatusDialogProps = {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    title: string;
    description: string;
    status: string;
    statusVariant?: DistributionStatusVariant;
    confirmLabel: string;
    confirmVariant?: 'default' | 'outline' | 'destructive';
    onConfirm: () => void;
    pending?: boolean;
    className?: string;
    children?: ReactNode;
};

export function StatusDialog({
    open,
    onOpenChange,
    title,
    description,
    status,
    statusVariant = 'outline',
    confirmLabel,
    confirmVariant = 'default',
    onConfirm,
    pending = false,
    className,
    children,
}: StatusDialogProps) {
    return (
        <Dialog
            open={open}
            onOpenChange={(next) => {
                if (!pending) {
                    onOpenChange(next);
                }
            }}
        >
            <DialogContent className={className}>
                <DialogHeader>
                    <div className="flex flex-wrap items-center gap-2">
                        <DialogTitle>{title}</DialogTitle>
                        <Badge variant={statusVariant}>{status}</Badge>
                    </div>
                    <DialogDescription>{description}</DialogDescription>
                </DialogHeader>
                <p className="sr-only" role="status">
                    {status}
                </p>
                {children}
                <DialogFooter>
                    <DialogClose asChild>
                        <Button
                            type="button"
                            variant="outline"
                            disabled={pending}
                        >
                            Cancel
                        </Button>
                    </DialogClose>
                    <Button
                        type="button"
                        variant={confirmVariant}
                        disabled={pending}
                        onClick={onConfirm}
                    >
                        {pending ? <Spinner data-icon="inline-start" /> : null}
                        {confirmLabel}
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}

type ConfirmActionButtonProps = {
    label: string;
    title: string;
    description: string;
    status: string;
    statusVariant?: DistributionStatusVariant;
    confirmLabel?: string;
    confirmVariant?: 'default' | 'outline' | 'destructive';
    buttonVariant?: 'default' | 'outline' | 'destructive';
    disabled?: boolean;
    onConfirm: (visit: VisitCallbacks) => void;
};

export function ConfirmActionButton({
    label,
    title,
    description,
    status,
    statusVariant = 'outline',
    confirmLabel,
    confirmVariant = 'default',
    buttonVariant = 'default',
    disabled = false,
    onConfirm,
}: ConfirmActionButtonProps) {
    const [open, setOpen] = useState(false);
    const [pending, setPending] = useState(false);

    return (
        <>
            <Button
                type="button"
                variant={buttonVariant}
                disabled={disabled}
                onClick={() => setOpen(true)}
            >
                {label}
            </Button>
            <StatusDialog
                open={open}
                onOpenChange={setOpen}
                title={title}
                description={description}
                status={status}
                statusVariant={statusVariant}
                confirmLabel={confirmLabel ?? label}
                confirmVariant={confirmVariant}
                pending={pending}
                onConfirm={() =>
                    onConfirm({
                        onStart: () => setPending(true),
                        onSuccess: () => setOpen(false),
                        onFinish: () => setPending(false),
                    })
                }
            />
        </>
    );
}
