'use client';

import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { Label } from '@/components/ui/label';
import { index as adminUsersIndex } from '@/routes/admin/users';
import {
    create as adminWorkflowsCreate,
    index as adminWorkflowsIndex,
    show as adminWorkflowsShow,
} from '@/routes/admin/workflows';
import type { BreadcrumbItem } from '@/types';
import type { AdminDepartmentOption } from '@/types/admin-user';
import { Head, Link, router, setLayoutProps } from '@inertiajs/react';
import { Plus } from 'lucide-react';
import { useEffect } from 'react';

type WorkflowSummary = {
    id: number;
    name: string;
    code: string | null;
    version: number;
    status: string;
    status_label: string;
    is_default: boolean;
    department: { id: number; name: string; slug: string } | null;
};

type WorkflowAbilities = {
    create: boolean;
};

export default function AdminWorkflowsIndex({
    departments,
    department,
    workflows,
    can,
}: {
    departments: AdminDepartmentOption[];
    department: { id: number; name: string; slug: string } | null;
    workflows: WorkflowSummary[];
    can: WorkflowAbilities;
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
                query: slug === 'all' ? {} : { department: slug },
            }),
            {},
            {
                preserveState: true,
                preserveScroll: true,
                only: ['department', 'workflows', 'can'],
            },
        );
    };

    return (
        <>
            <Head title="Workflows" />

            <div className="space-y-4">
                <div className="flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
                    <div className="max-w-sm space-y-2">
                        <Label htmlFor="admin-workflow-department">
                            Department
                        </Label>
                        <Select
                            value={department?.slug ?? 'all'}
                            onValueChange={visitDepartment}
                        >
                            <SelectTrigger id="admin-workflow-department">
                                <SelectValue placeholder="All departments" />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value="all">
                                    All departments
                                </SelectItem>
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

                    {can.create ? (
                        <Button asChild>
                            <Link href={adminWorkflowsCreate.url()}>
                                <Plus className="size-4" />
                                Create Workflow
                            </Link>
                        </Button>
                    ) : null}
                </div>

                <Table>
                    <TableHeader>
                        <TableRow>
                            <TableHead>Name</TableHead>
                            <TableHead>Version</TableHead>
                            <TableHead>Status</TableHead>
                            <TableHead>Department</TableHead>
                        </TableRow>
                    </TableHeader>
                    <TableBody>
                        {workflows.length === 0 ? (
                            <TableRow>
                                <TableCell
                                    colSpan={4}
                                    className="text-muted-foreground"
                                >
                                    No workflows yet.
                                </TableCell>
                            </TableRow>
                        ) : (
                            workflows.map((workflow) => (
                                <TableRow key={workflow.id}>
                                    <TableCell>
                                        <Link
                                            href={adminWorkflowsShow.url(
                                                workflow.id,
                                            )}
                                            className="font-medium hover:underline"
                                        >
                                            {workflow.name}
                                        </Link>
                                        {workflow.code ? (
                                            <p className="text-xs text-muted-foreground">
                                                {workflow.code}
                                            </p>
                                        ) : null}
                                    </TableCell>
                                    <TableCell>v{workflow.version}</TableCell>
                                    <TableCell>
                                        <Badge variant="secondary">
                                            {workflow.status_label}
                                        </Badge>
                                    </TableCell>
                                    <TableCell>
                                        {workflow.department?.name ?? '—'}
                                    </TableCell>
                                </TableRow>
                            ))
                        )}
                    </TableBody>
                </Table>
            </div>
        </>
    );
}
