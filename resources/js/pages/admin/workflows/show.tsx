'use client';

import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { WorkflowEditor } from '@/pages/user/workflows/workflow-editor';
import type {
    WorkflowDepartmentSummary,
    WorkflowPayload,
    WorkflowReason,
    WorkflowStaffOption,
    WorkflowStatus,
} from '@/pages/user/workflows/workflow-editor';
import { index as adminUsersIndex } from '@/routes/admin/users';
import {
    index as adminWorkflowsIndex,
    assignPrograms as assignWorkflowPrograms,
} from '@/routes/admin/workflows';
import type { BreadcrumbItem } from '@/types';
import type { AdminDepartmentOption } from '@/types/admin-user';
import { Form, Head, setLayoutProps } from '@inertiajs/react';
import { useEffect, useState } from 'react';
import { toast } from 'sonner';

type WorkflowAbilities = {
    create: boolean;
    update: boolean;
    publish: boolean;
    manage: boolean;
    version: boolean;
    assign: boolean;
    delete: boolean;
    duplicate: boolean;
};

type DepartmentProgram = {
    id: number;
    name: string;
    workflow_id: number | null;
};

type RoleOption = {
    value: string;
    label: string;
};

export default function AdminWorkflowShow({
    department,
    departments,
    workflow,
    statuses,
    reasons,
    staff_options = [],
    role_options = [],
    department_programs = [],
    can,
}: {
    department: WorkflowDepartmentSummary;
    departments: AdminDepartmentOption[];
    workflow: WorkflowPayload;
    statuses: WorkflowStatus[];
    reasons: WorkflowReason[];
    staff_options?: WorkflowStaffOption[];
    role_options?: RoleOption[];
    department_programs?: DepartmentProgram[];
    can: WorkflowAbilities;
}) {
    const [selectedProgramIds, setSelectedProgramIds] = useState<number[]>(
        () => workflow.programs?.map((program) => program.id) ?? [],
    );

    useEffect(() => {
        setSelectedProgramIds(
            workflow.programs?.map((program) => program.id) ?? [],
        );
    }, [workflow]);

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
                {
                    title: workflow.name,
                    href: adminWorkflowsIndex.url(),
                },
            ] satisfies BreadcrumbItem[],
        });
    }, [workflow.name]);

    const toggleProgram = (programId: number) => {
        setSelectedProgramIds((current) =>
            current.includes(programId)
                ? current.filter((id) => id !== programId)
                : [...current, programId],
        );
    };

    return (
        <>
            <Head title={workflow.name} />
            <div className="space-y-4">
                <WorkflowEditor
                    department={department}
                    departments={departments}
                    workflows={[workflow]}
                    statuses={statuses}
                    reasons={reasons}
                    staff_options={staff_options}
                    role_options={role_options}
                    variant="admin"
                    can={can}
                />

                {can.assign ? (
                    <Card>
                        <CardHeader>
                            <CardTitle className="text-lg">Programs</CardTitle>
                        </CardHeader>
                        <CardContent>
                            <Form
                                {...assignWorkflowPrograms.form.put(
                                    workflow.id,
                                )}
                                disableWhileProcessing
                                options={{ preserveScroll: true }}
                                transform={() => ({
                                    program_ids: selectedProgramIds,
                                })}
                                onSuccess={() =>
                                    toast.success('Workflow programs updated.')
                                }
                                className="space-y-3"
                            >
                                {({ errors, processing }) => (
                                    <>
                                        {department_programs.length === 0 ? (
                                            <p className="text-sm text-muted-foreground">
                                                This department has no programs
                                                yet.
                                            </p>
                                        ) : (
                                            <ul className="space-y-2">
                                                {department_programs.map(
                                                    (program) => (
                                                        <li key={program.id}>
                                                            <label className="flex items-center gap-2 text-sm">
                                                                <input
                                                                    type="checkbox"
                                                                    className="size-4"
                                                                    checked={selectedProgramIds.includes(
                                                                        program.id,
                                                                    )}
                                                                    onChange={() =>
                                                                        toggleProgram(
                                                                            program.id,
                                                                        )
                                                                    }
                                                                />
                                                                <span>
                                                                    {
                                                                        program.name
                                                                    }
                                                                </span>
                                                            </label>
                                                        </li>
                                                    ),
                                                )}
                                            </ul>
                                        )}
                                        <InputError
                                            message={errors.program_ids}
                                        />
                                        <p className="text-sm text-muted-foreground">
                                            Only an active workflow can be
                                            assigned to programs. Existing
                                            requests keep the version they
                                            started on.
                                        </p>
                                        <Button
                                            type="submit"
                                            disabled={processing}
                                        >
                                            {processing
                                                ? 'Saving...'
                                                : 'Save program assignment'}
                                        </Button>
                                    </>
                                )}
                            </Form>
                        </CardContent>
                    </Card>
                ) : null}
            </div>
        </>
    );
}
