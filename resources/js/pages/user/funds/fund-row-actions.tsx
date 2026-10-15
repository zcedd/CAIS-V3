'use client';

import { router } from '@inertiajs/react';
import { Edit, MoreHorizontal, Trash } from 'lucide-react';
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
import { useCan } from '@/hooks/use-can';
import { Permission } from '@/lib/permissions';
import { FundEditDrawer } from '@/pages/user/funds/fund-edit-drawer';
import { destroy as destroyFund } from '@/routes/user/funds';
import type { FundRow } from '@/types/fund';

type FundRowActionsProps = {
    fund: FundRow;
    departmentSlug: string;
};

export function FundRowActions({ fund, departmentSlug }: FundRowActionsProps) {
    const { can } = useCan();
    const canEdit = can(Permission.FundUpdate);
    const canDelete = can(Permission.FundDelete);
    const [editOpen, setEditOpen] = useState(false);
    const [deleteOpen, setDeleteOpen] = useState(false);
    const [isDeleting, setIsDeleting] = useState(false);

    const handleDelete = () => {
        router.delete(
            destroyFund.url({
                department: departmentSlug,
                fund: fund.id,
            }),
            {
                preserveScroll: true,
                onStart: () => setIsDeleting(true),
                onFinish: () => setIsDeleting(false),
                onSuccess: () => {
                    setDeleteOpen(false);
                    toast.success('Fund deleted successfully.');
                },
                onError: (errors) => {
                    const message =
                        typeof errors.fund === 'string'
                            ? errors.fund
                            : 'Unable to delete this fund.';
                    toast.error(message);
                },
            },
        );
    };

    if (!canEdit && !canDelete) {
        return null;
    }

    return (
        <>
            <DropdownMenu>
                <DropdownMenuTrigger asChild>
                    <Button
                        variant="ghost"
                        className="flex size-8 p-0 data-[state=open]:bg-muted"
                    >
                        <MoreHorizontal className="h-4 w-4" />
                        <span className="sr-only">Open menu</span>
                    </Button>
                </DropdownMenuTrigger>
                <DropdownMenuContent align="end" className="w-[160px]">
                    {canEdit ? (
                        <DropdownMenuItem onSelect={() => setEditOpen(true)}>
                            <Edit className="mr-2 h-4 w-4" />
                            Edit fund
                        </DropdownMenuItem>
                    ) : null}
                    {canDelete ? (
                        <DropdownMenuItem
                            variant="destructive"
                            onSelect={(event) => {
                                event.preventDefault();
                                setDeleteOpen(true);
                            }}
                        >
                            <Trash className="mr-2 h-4 w-4" />
                            Delete fund
                        </DropdownMenuItem>
                    ) : null}
                </DropdownMenuContent>
            </DropdownMenu>

            <FundEditDrawer
                open={editOpen}
                onOpenChange={setEditOpen}
                fund={fund}
                departmentSlug={departmentSlug}
            />

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
                        <DialogTitle>Delete fund?</DialogTitle>
                        <DialogDescription>
                            This will remove{' '}
                            <span className="font-medium text-foreground">
                                {fund.name}
                            </span>
                            . Funds linked to programs cannot be deleted.
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
                            Delete fund
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>
        </>
    );
}
