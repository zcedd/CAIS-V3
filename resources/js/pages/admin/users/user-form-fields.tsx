'use client';

import InputError from '@/components/input-error';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import type { AdminDepartmentOption, AdminRoleOption, AdminUserRow } from '@/types/admin-user';
import { useState } from 'react';

type UserFormFieldsProps = {
    user?: AdminUserRow | null;
    departments: AdminDepartmentOption[];
    roleOptions: AdminRoleOption[];
    errors: Record<string, string>;
    includePassword?: boolean;
    idPrefix: string;
};

export function UserFormFields({
    user,
    departments,
    roleOptions,
    errors,
    includePassword = false,
    idPrefix,
}: UserFormFieldsProps) {
    const [departmentId, setDepartmentId] = useState(
        user?.department?.id ? String(user.department.id) : 'none',
    );

    return (
        <>
            <div className="space-y-2">
                <Label htmlFor={`${idPrefix}-first-name`}>First name</Label>
                <Input
                    id={`${idPrefix}-first-name`}
                    name="firstName"
                    defaultValue={user?.firstName ?? ''}
                    autoComplete="given-name"
                />
                <InputError message={errors.firstName} />
            </div>

            <div className="space-y-2">
                <Label htmlFor={`${idPrefix}-middle-name`}>Middle name</Label>
                <Input
                    id={`${idPrefix}-middle-name`}
                    name="middleName"
                    defaultValue={user?.middleName ?? ''}
                    autoComplete="additional-name"
                />
                <InputError message={errors.middleName} />
            </div>

            <div className="space-y-2">
                <Label htmlFor={`${idPrefix}-last-name`}>Last name</Label>
                <Input
                    id={`${idPrefix}-last-name`}
                    name="lastName"
                    defaultValue={user?.lastName ?? ''}
                    autoComplete="family-name"
                />
                <InputError message={errors.lastName} />
            </div>

            <div className="space-y-2">
                <Label htmlFor={`${idPrefix}-email`}>Email</Label>
                <Input
                    id={`${idPrefix}-email`}
                    name="email"
                    type="email"
                    defaultValue={user?.email ?? ''}
                    autoComplete="email"
                />
                <InputError message={errors.email} />
            </div>

            {includePassword ? (
                <>
                    <div className="space-y-2">
                        <Label htmlFor={`${idPrefix}-password`}>
                            New password
                        </Label>
                        <Input
                            id={`${idPrefix}-password`}
                            name="password"
                            type="password"
                            autoComplete="new-password"
                            placeholder="Leave blank to keep the current password"
                        />
                        <InputError message={errors.password} />
                    </div>

                    <div className="space-y-2">
                        <Label htmlFor={`${idPrefix}-password-confirmation`}>
                            Confirm password
                        </Label>
                        <Input
                            id={`${idPrefix}-password-confirmation`}
                            name="password_confirmation"
                            type="password"
                            autoComplete="new-password"
                        />
                    </div>
                </>
            ) : null}

            <div className="space-y-2">
                <Label htmlFor={`${idPrefix}-department`}>Department</Label>
                <input
                    type="hidden"
                    name="department_id"
                    value={departmentId === 'none' ? '' : departmentId}
                />
                <Select value={departmentId} onValueChange={setDepartmentId}>
                    <SelectTrigger id={`${idPrefix}-department`}>
                        <SelectValue placeholder="No department" />
                    </SelectTrigger>
                    <SelectContent>
                        <SelectItem value="none">No department</SelectItem>
                        {departments.map((department) => (
                            <SelectItem
                                key={department.id}
                                value={String(department.id)}
                            >
                                {department.name}
                            </SelectItem>
                        ))}
                    </SelectContent>
                </Select>
                <InputError message={errors.department_id} />
            </div>

            <fieldset className="space-y-2">
                <legend className="text-sm font-medium">Roles</legend>
                <div className="grid gap-2">
                    {roleOptions.map((role) => (
                        <label
                            key={role.value}
                            className="flex items-center gap-2 text-sm"
                        >
                            <Input
                                type="checkbox"
                                name="roles[]"
                                value={role.value}
                                defaultChecked={user?.roles.includes(role.value)}
                                className="size-4 shrink-0 rounded border-input"
                            />
                            {role.label}
                        </label>
                    ))}
                </div>
                <InputError message={errors.roles} />
            </fieldset>
        </>
    );
}
