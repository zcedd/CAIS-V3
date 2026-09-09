'use client';

import { WorkflowEditor } from '@/pages/user/workflows/workflow-editor';
import type {
    WorkflowDepartmentSummary,
    WorkflowPayload,
    WorkflowReason,
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
    can_create = false,
}: {
    department: WorkflowDepartmentSummary;
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
                        The stages a request can move through. Programs use the
                        department default unless they pick another workflow.
                    </p>
                </div>

                <WorkflowEditor
                    department={department}
                    workflows={workflows}
                    statuses={statuses}
                    reasons={reasons}
                    staff_options={staff_options}
                    can_create={can_create}
                />
            </div>
        </>
    );
}
