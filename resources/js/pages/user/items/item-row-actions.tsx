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
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import type { UserDepartmentItemRow } from '@/pages/user/items/item-columns';
import { ItemEditDrawer } from '@/pages/user/items/item-edit-drawer';
import type { UnitMeasurementOption } from '@/pages/user/items/item-toolbar';
import { destroy as destroyDepartmentItem } from '@/routes/user/items';
import { router } from '@inertiajs/react';
import { Row } from '@tanstack/react-table';
import { Edit, MoreHorizontal, Trash } from 'lucide-react';
import { useState } from 'react';
import { toast } from 'sonner';

interface ItemDataTableRowActionsProps {
    row: Row<UserDepartmentItemRow>;
    departmentSlug: string;
    unitMeasurements: UnitMeasurementOption[];
    onItemUpdated?: () => void;
}

export function ItemDataTableRowActions({
    row,
    departmentSlug,
    unitMeasurements,
    onItemUpdated,
}: ItemDataTableRowActionsProps) {
    const record = row.original;
    const [editOpen, setEditOpen] = useState(false);
    const [deleteOpen, setDeleteOpen] = useState(false);
    const [isDeleting, setIsDeleting] = useState(false);

    const handleDelete = () => {
        router.delete(
            destroyDepartmentItem.url({
                department: departmentSlug,
                item: record.id,
            }),
            {
                preserveScroll: true,
                onStart: () => setIsDeleting(true),
                onFinish: () => setIsDeleting(false),
                onSuccess: () => {
                    setDeleteOpen(false);
                    toast.success('Item deleted successfully.');
                    onItemUpdated?.();
                },
            },
        );
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
                <DropdownMenuContent align="end" className="w-[160px]">
                    <DropdownMenuItem onSelect={() => setEditOpen(true)}>
                        <Edit className="mr-2 h-4 w-4" />
                        Edit item
                    </DropdownMenuItem>
                    <DropdownMenuSeparator />
                    <DropdownMenuItem
                        variant="destructive"
                        onSelect={(event) => {
                            event.preventDefault();
                            setDeleteOpen(true);
                        }}
                    >
                        <Trash className="mr-2 h-4 w-4" />
                        Delete item
                    </DropdownMenuItem>
                </DropdownMenuContent>
            </DropdownMenu>

            <ItemEditDrawer
                open={editOpen}
                onOpenChange={setEditOpen}
                item={record}
                departmentSlug={departmentSlug}
                unitMeasurements={unitMeasurements}
                onUpdated={onItemUpdated}
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
                        <DialogTitle>Delete item?</DialogTitle>
                        <DialogDescription>
                            This will remove{' '}
                            <span className="font-medium text-foreground">
                                {record.name}
                            </span>
                            . Items linked to programs or assistances cannot be
                            deleted.
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
                            Delete item
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>
        </>
    );
}
