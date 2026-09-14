'use client';

import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { WorkflowEditor } from '@/pages/user/workflows/workflow-editor';
import type {
    WorkflowDepartmentSummary,
    WorkflowPayload,
    WorkflowReason,
    WorkflowStaffOption,
    WorkflowStatus,
} from '@/pages/user/workflows/workflow-editor';
import { index as adminUsersIndex } from '@/routes/admin/users';
import { index as adminWorkflowsIndex } from '@/routes/admin/workflows';
import type { BreadcrumbItem } from '@/types';
import type { AdminDepartmentOption } from '@/types/admin-user';
import { Head, router, setLayoutProps } from '@inertiajs/react';
import { useEffect } from 'react';

export default function AdminWorkflowsIndex({
    departments,
    department,
    workflows,
    statuses,
    reasons,
    staff_options = [],
    can_create = false,
}: {
    departments: AdminDepartmentOption[];
    department: WorkflowDepartmentSummary | null;
    workflows: WorkflowPayload[];
    statuses: WorkflowStatus[];
    reasons: WorkflowReason[];
    staff_options?: WorkflowStaffOption[];
    can_create?: boolean;
}) {
    useEffect(() => {
        setLayoutProps({
            breadcrumbs: [
                {
                    title: 'Administration',
                    href: adminUsersIndex.url(),
                },
                {
                    title: 'Workflows',
                    href: adminWorkflowsIndex.url(),
                },
            ] satisfies BreadcrumbItem[],
        });
    }, []);

    const visitDepartment = (slug: string) => {
        router.get(
            adminWorkflowsIndex.url({
                query: { department: slug },
            }),
            {},
            {
                preserveState: true,
                preserveScroll: true,
                only: [
                    'department',
                    'workflows',
                    'statuses',
                    'reasons',
                    'staff_options',
                    'can_create',
                ],
            },
        );
    };

    return (
        <>
            <Head title="Workflows" />

            <div className="space-y-4">
                <div className="max-w-sm space-y-2">
                    <Label htmlFor="admin-workflow-department">
                        Department
                    </Label>
                    <Select
                        value={department?.slug}
                        onValueChange={visitDepartment}
                    >
                        <SelectTrigger id="admin-workflow-department">
                            <SelectValue placeholder="Select a department" />
                        </SelectTrigger>
                        <SelectContent>
                            {departments.map((option) => (
                                <SelectItem
                                    key={option.id}
                                    value={option.slug}
                                >
                                    {option.name}
                                </SelectItem>
                            ))}
                        </SelectContent>
                    </Select>
                </div>

                {department ? (
                    <WorkflowEditor
                        key={department.slug}
                        department={department}
                        workflows={workflows}
                        statuses={statuses}
                        reasons={reasons}
                        staff_options={staff_options}
                        can_create={can_create}
                    />
                ) : (
                    <p className="text-sm text-muted-foreground">
                        Select a department to view and edit its workflows.
                    </p>
                )}
            </div>
        </>
    );
}
