'use client';

import { router } from '@inertiajs/react';
import { MoreHorizontal, Pencil, Trash2 } from 'lucide-react';
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
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import type { DistributionPolicy } from '@/pages/admin/distribution-policies/policy-columns';
import { destroy as destroyDistributionPolicy } from '@/routes/admin/distribution-policies';

export function DistributionPolicyRowActions({
    policy,
    onEdit,
}: {
    policy: DistributionPolicy;
    onEdit: (policy: DistributionPolicy) => void;
}) {
    const [deleteOpen, setDeleteOpen] = useState(false);
    const [isDeleting, setIsDeleting] = useState(false);

    const handleDelete = () => {
        router.delete(destroyDistributionPolicy.url(policy.id), {
            preserveScroll: true,
            onStart: () => setIsDeleting(true),
            onFinish: () => setIsDeleting(false),
            onSuccess: () => {
                setDeleteOpen(false);
                toast.success('Approval policy deleted.');
            },
            onError: () => {
                toast.error('Unable to delete this policy.');
            },
        });
    };

    return (
        <>
            <DropdownMenu>
                <DropdownMenuTrigger asChild>
                    <Button variant="ghost" size="icon-sm">
                        <MoreHorizontal />
                        <span className="sr-only">Open menu</span>
                    </Button>
                </DropdownMenuTrigger>
                <DropdownMenuContent align="end" className="w-44">
                    <DropdownMenuItem onSelect={() => onEdit(policy)}>
                        <Pencil />
                        Edit
                    </DropdownMenuItem>
                    <DropdownMenuItem
                        variant="destructive"
                        onSelect={(event) => {
                            event.preventDefault();
                            setDeleteOpen(true);
                        }}
                    >
                        <Trash2 />
                        Delete
                    </DropdownMenuItem>
                </DropdownMenuContent>
            </DropdownMenu>

            <Dialog
                open={deleteOpen}
                onOpenChange={(open) => {
                    if (!isDeleting) {
                        setDeleteOpen(open);
                    }
                }}
            >
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>Delete policy?</DialogTitle>
                        <DialogDescription>
                            {policy.name} will no longer apply to new batches.
                            Batches that already use it keep their approval
                            record.
                        </DialogDescription>
                    </DialogHeader>
                    <DialogFooter>
                        <DialogClose asChild>
                            <Button variant="outline" disabled={isDeleting}>
                                Cancel
                            </Button>
                        </DialogClose>
                        <Button
                            variant="destructive"
                            disabled={isDeleting}
                            onClick={handleDelete}
                        >
                            {isDeleting ? 'Deleting...' : 'Delete policy'}
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>
        </>
    );
}
