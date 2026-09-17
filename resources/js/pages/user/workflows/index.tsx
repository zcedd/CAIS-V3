'use client';

import { WorkflowEditor } from '@/pages/user/workflows/workflow-editor';
import type {
    WorkflowAbilities,
    WorkflowDepartmentSummary,
    WorkflowPayload,
    WorkflowReason,
    WorkflowRoleOption,
    WorkflowStaffOption,
    WorkflowStatus,
} from '@/pages/user/workflows/workflow-editor';
import { index as departmentWorkflowsIndex } from '@/routes/user/workflows';
import type { BreadcrumbItem } from '@/types';
import { Head, setLayoutProps } from '@inertiajs/react';
import { useEffect } from 'react';

export default function UserWorkflowsIndex({
    department,
    workflows,
    statuses,
    reasons,
    staff_options = [],
    role_options = [],
    can_create = false,
    can = {},
}: {
    department: WorkflowDepartmentSummary;
    workflows: WorkflowPayload[];
    statuses: WorkflowStatus[];
    reasons: WorkflowReason[];
    staff_options?: WorkflowStaffOption[];
    role_options?: WorkflowRoleOption[];
    can_create?: boolean;
    can?: WorkflowAbilities;
}) {
    useEffect(() => {
        setLayoutProps({
            breadcrumbs: [
                {
                    title: 'Workflows',
                    href: departmentWorkflowsIndex.url(department.slug),
                },
            ] satisfies BreadcrumbItem[],
        });
    }, [department.slug]);

    return (
        <>
            <Head title="Workflows" />
            <div className="flex h-full min-w-0 flex-1 flex-col gap-4 overflow-x-hidden rounded-xl p-4">
                <div>
                    <h1 className="text-2xl font-semibold tracking-tight">
                        Workflows
                    </h1>
                    <p className="text-sm text-muted-foreground">
                        The stages a request can move through. Program
                        administrators cannot change the assigned workflow.
                    </p>
                </div>

                <WorkflowEditor
                    department={department}
                    workflows={workflows}
                    statuses={statuses}
                    reasons={reasons}
                    staff_options={staff_options}
                    role_options={role_options}
                    can_create={can_create}
                    can={can}
                />
            </div>
        </>
    );
}
