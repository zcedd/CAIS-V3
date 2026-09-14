'use client';

import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import {
    Drawer,
    DrawerClose,
    DrawerContent,
    DrawerDescription,
    DrawerFooter,
    DrawerHeader,
    DrawerTitle,
} from '@/components/ui/drawer';
import { UserFormFields } from '@/pages/admin/users/user-form-fields';
import { update as updateAdminUser } from '@/routes/admin/users';
import type {
    AdminDepartmentOption,
    AdminRoleOption,
    AdminUserRow,
} from '@/types/admin-user';
import { Form } from '@inertiajs/react';
import { toast } from 'sonner';

type UserEditDrawerProps = {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    user: AdminUserRow | null;
    departments: AdminDepartmentOption[];
    roleOptions: AdminRoleOption[];
};

export function UserEditDrawer({
    open,
    onOpenChange,
    user,
    departments,
    roleOptions,
}: UserEditDrawerProps) {
    if (!user) {
        return null;
    }

    return (
        <Drawer open={open} onOpenChange={onOpenChange} direction="right">
            <DrawerContent className="data-[vaul-drawer-direction=right]:sm:max-w-lg">
                <DrawerHeader>
                    <DrawerTitle>Edit user</DrawerTitle>
                    <DrawerDescription>
                        Update the account, department, and roles for {user.name}.
                    </DrawerDescription>
                </DrawerHeader>
                <Form
                    key={user.id}
                    {...updateAdminUser.form(user.id)}
                    disableWhileProcessing
                    onSuccess={() => {
                        onOpenChange(false);
                        toast.success('User updated.');
                    }}
                    className="flex flex-1 flex-col gap-4 overflow-y-auto px-4"
                >
                    {({ errors, processing }) => (
                        <>
                            <UserFormFields
                                user={user}
                                departments={departments}
                                roleOptions={roleOptions}
                                errors={errors}
                                includePassword
                                idPrefix={`edit-user-${user.id}`}
                            />
                            <InputError message={errors.user} />
                            <DrawerFooter className="px-0">
                                <Button type="submit" disabled={processing}>
                                    {processing ? 'Saving...' : 'Save changes'}
                                </Button>
                                <DrawerClose asChild>
                                    <Button type="button" variant="outline">
                                        Cancel
                                    </Button>
                                </DrawerClose>
                            </DrawerFooter>
                        </>
                    )}
                </Form>
            </DrawerContent>
        </Drawer>
    );
}
