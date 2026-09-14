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
import { UserEditDrawer } from '@/pages/admin/users/user-edit-drawer';
import { destroy as destroyAdminUser } from '@/routes/admin/users';
import type {
    AdminDepartmentOption,
    AdminRoleOption,
    AdminUserRow,
} from '@/types/admin-user';
import { router } from '@inertiajs/react';
import { Edit, MoreHorizontal, Trash } from 'lucide-react';
import { useState } from 'react';
import { toast } from 'sonner';

type UserRowActionsProps = {
    user: AdminUserRow;
    departments: AdminDepartmentOption[];
    roleOptions: AdminRoleOption[];
    currentUserId: number;
};

export function UserRowActions({
    user,
    departments,
    roleOptions,
    currentUserId,
}: UserRowActionsProps) {
    const [editOpen, setEditOpen] = useState(false);
    const [deleteOpen, setDeleteOpen] = useState(false);
    const [isDeleting, setIsDeleting] = useState(false);
    const isCurrentUser = user.id === currentUserId;

    const handleDelete = () => {
        router.delete(destroyAdminUser.url(user.id), {
            preserveScroll: true,
            onStart: () => setIsDeleting(true),
            onFinish: () => setIsDeleting(false),
            onSuccess: () => {
                setDeleteOpen(false);
                toast.success('User deleted.');
            },
            onError: (errors) => {
                const message =
                    typeof errors.user === 'string'
                        ? errors.user
                        : 'Unable to delete this user.';
                toast.error(message);
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
                <DropdownMenuContent align="end" className="w-40">
                    <DropdownMenuItem onSelect={() => setEditOpen(true)}>
                        <Edit className="mr-2 h-4 w-4" />
                        Edit user
                    </DropdownMenuItem>
                    <DropdownMenuItem
                        variant="destructive"
                        disabled={isCurrentUser}
                        onSelect={(event) => {
                            event.preventDefault();

                            if (isCurrentUser) {
                                return;
                            }

                            setDeleteOpen(true);
                        }}
                    >
                        <Trash className="mr-2 h-4 w-4" />
                        Delete user
                    </DropdownMenuItem>
                </DropdownMenuContent>
            </DropdownMenu>

            <UserEditDrawer
                open={editOpen}
                onOpenChange={setEditOpen}
                user={user}
                departments={departments}
                roleOptions={roleOptions}
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
                        <DialogTitle>Delete user?</DialogTitle>
                        <DialogDescription>
                            This will remove{' '}
                            <span className="font-medium text-foreground">
                                {user.name}
                            </span>
                            . They will no longer be able to sign in.
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
                            Delete user
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>
        </>
    );
}
