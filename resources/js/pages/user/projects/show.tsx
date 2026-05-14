import { DataTable } from '@/components/data-table';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import {
    userProjectAssistanceColumns,
    type UserProjectAssistanceRow,
} from '@/pages/user/projects/assistance-columns';
import {
    index as departmentProjectsIndex,
    show as departmentProjectShow,
} from '@/routes/user/projects';
import type { BreadcrumbItem } from '@/types';
import { Head, Link, setLayoutProps } from '@inertiajs/react';
import { useEffect } from 'react';

type DepartmentSummary = {
    id: number;
    name: string;
    slug: string;
};

type ProjectDetail = {
    id: number;
    name: string;
    descriptions: string | null;
    start_at: string | null;
    end_at: string | null;
    is_closed: boolean | null;
    is_organization: boolean | null;
    department_id: number;
    department?: DepartmentSummary | null;
};

export default function UserProjectShow({
    project,
    department,
    assistances,
}: {
    project: ProjectDetail;
    department: DepartmentSummary | null;
    assistances: UserProjectAssistanceRow[];
}) {
    useEffect(() => {
        if (!department?.slug) {
            return;
        }

        const projectsHref = departmentProjectsIndex.url(department.slug);
        const selfHref = departmentProjectShow.url({
            department: department.slug,
            project: project.id,
        });

        setLayoutProps({
            breadcrumbs: [
                {
                    title: 'Projects',
                    href: projectsHref,
                },
                {
                    title: project.name,
                    href: selfHref,
                },
            ] satisfies BreadcrumbItem[],
        });
    }, [department?.slug, project.id, project.name]);

    const heading = project.name;

    return (
        <>
            <Head title={heading} />
            <div className="flex h-full flex-1 flex-col gap-4 overflow-x-auto rounded-xl p-4">
                <div className="flex flex-col gap-2 sm:flex-row sm:items-end sm:justify-between">
                    <div>
                        <h1 className="text-2xl font-semibold tracking-tight">
                            {heading}
                        </h1>
                        <p className="text-sm text-muted-foreground">
                            {department
                                ? `${department.name} project details.`
                                : 'Project details.'}
                        </p>
                    </div>
                    {department?.slug ? (
                        <Button variant="outline" asChild>
                            <Link
                                href={departmentProjectsIndex.url(
                                    department.slug,
                                )}
                            >
                                Back to projects
                            </Link>
                        </Button>
                    ) : null}
                </div>
                <Card>
                    <CardHeader className="gap-1">
                        <CardTitle className="text-lg">Overview</CardTitle>
                        <CardDescription>
                            {project.is_organization
                                ? 'Organization'
                                : 'Personal'}
                        </CardDescription>
                    </CardHeader>
                    <CardContent className="flex flex-col gap-3 text-sm text-muted-foreground">
                        <p className="whitespace-pre-wrap">
                            {project.descriptions ?? '—'}
                        </p>
                        <p>
                            <span className="font-medium text-foreground">
                                Period:{' '}
                            </span>
                            {project.start_at ?? '—'}
                            {project.end_at ? ` – ${project.end_at}` : ''}
                        </p>
                        <p>
                            <span className="font-medium text-foreground">
                                Status:{' '}
                            </span>
                            {project.is_closed ? 'Closed' : 'Open'}
                        </p>
                    </CardContent>
                </Card>
                <Card>
                    <CardHeader className="gap-1">
                        <CardTitle className="text-lg">Assistance</CardTitle>
                        <CardDescription>
                            Records linked to this project (sortable columns).
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        <DataTable
                            columns={userProjectAssistanceColumns}
                            data={assistances}
                            emptyMessage="No assistance records for this project."
                        />
                    </CardContent>
                </Card>
            </div>
        </>
    );
}
