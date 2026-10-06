'use client';

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
import { show as adminWorkflowsShow, destroy as destroyAdminWorkflow } from '@/routes/admin/workflows';
import type { AdminWorkflowRow } from '@/types/admin-workflow';
import { router } from '@inertiajs/react';
import { Edit, MoreHorizontal, Trash } from 'lucide-react';
import { useState } from 'react';
import { toast } from 'sonner';

type WorkflowRowActionsProps = {
    workflow: AdminWorkflowRow;
    canDelete: boolean;
};

export function WorkflowRowActions({
    workflow,
    canDelete,
}: WorkflowRowActionsProps) {
    const [deleteOpen, setDeleteOpen] = useState(false);
    const [isDeleting, setIsDeleting] = useState(false);

    const handleDelete = () => {
        router.delete(destroyAdminWorkflow.url(workflow.id), {
            preserveScroll: true,
            onStart: () => setIsDeleting(true),
            onFinish: () => setIsDeleting(false),
            onSuccess: () => {
                setDeleteOpen(false);
                toast.success(
                    workflow.is_referenced
                        ? 'Workflow is in use, so it was deactivated instead of deleted.'
                        : 'Workflow removed.',
                );
            },
            onError: () => {
                toast.error('Unable to remove this workflow.');
            },
        });
    };

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
                <DropdownMenuContent align="end" className="w-44">
                    <DropdownMenuItem
                        onSelect={() => {
                            router.visit(adminWorkflowsShow.url(workflow.id));
                        }}
                    >
                        <Edit className="mr-2 h-4 w-4" />
                        Edit workflow
                    </DropdownMenuItem>
                    {canDelete ? (
                        <DropdownMenuItem
                            variant="destructive"
                            onSelect={(event) => {
                                event.preventDefault();
                                setDeleteOpen(true);
                            }}
                        >
                            <Trash className="mr-2 h-4 w-4" />
                            Remove workflow
                        </DropdownMenuItem>
                    ) : null}
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
                        <DialogTitle>Remove workflow?</DialogTitle>
                        <DialogDescription>
                            This will remove{' '}
                            <span className="font-medium text-foreground">
                                {workflow.name}
                            </span>
                            .
                            {workflow.is_referenced
                                ? ' It is already in use, so it will be deactivated instead of deleted.'
                                : ' This cannot be undone.'}
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
                            Remove workflow
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>
        </>
    );
}
