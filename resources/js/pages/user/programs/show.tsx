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
    userProgramAssistanceColumns,
    type UserProgramAssistanceRow,
} from '@/pages/user/programs/assistance-columns';
import {
    index as departmentProgramsIndex,
    show as departmentProgramShow,
} from '@/routes/user/programs';
import type { BreadcrumbItem } from '@/types';
import { Head, Link, setLayoutProps } from '@inertiajs/react';
import { useEffect } from 'react';

type DepartmentSummary = {
    id: number;
    name: string;
    slug: string;
};

type ProgramDetail = {
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

type PaginatedAssistances = {
    data: UserProgramAssistanceRow[];
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
    from: number | null;
    to: number | null;
    prev_page_url: string | null;
    next_page_url: string | null;
};

export default function UserProgramShow({
    program,
    department,
    assistances,
}: {
    program: ProgramDetail;
    department: DepartmentSummary | null;
    assistances: PaginatedAssistances;
}) {
    useEffect(() => {
        if (!department?.slug) {
            return;
        }

        const programsHref = departmentProgramsIndex.url(department.slug);
        const selfHref = departmentProgramShow.url({
            department: department.slug,
            program: program.id,
        });

        setLayoutProps({
            breadcrumbs: [
                {
                    title: 'Programs',
                    href: programsHref,
                },
                {
                    title: program.name,
                    href: selfHref,
                },
            ] satisfies BreadcrumbItem[],
        });
    }, [department?.slug, program.id, program.name]);

    const heading = program.name;

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
                                ? `${department.name} program details.`
                                : 'Program details.'}
                        </p>
                    </div>
                    {department?.slug ? (
                        <Button variant="outline" asChild>
                            <Link
                                href={departmentProgramsIndex.url(
                                    department.slug,
                                )}
                            >
                                Back to programs
                            </Link>
                        </Button>
                    ) : null}
                </div>
                <Card>
                    <CardHeader className="gap-1">
                        <CardTitle className="text-lg">Overview</CardTitle>
                        <CardDescription>
                            {program.is_organization
                                ? 'Organization'
                                : 'Personal'}
                        </CardDescription>
                    </CardHeader>
                    <CardContent className="flex flex-col gap-3 text-sm text-muted-foreground">
                        <p className="whitespace-pre-wrap">
                            {program.descriptions ?? '—'}
                        </p>
                        <p>
                            <span className="font-medium text-foreground">
                                Period:{' '}
                            </span>
                            {program.start_at ?? '—'}
                            {program.end_at ? ` – ${program.end_at}` : ''}
                        </p>
                        <p>
                            <span className="font-medium text-foreground">
                                Status:{' '}
                            </span>
                            {program.is_closed ? 'Closed' : 'Open'}
                        </p>
                    </CardContent>
                </Card>
                <Card>
                    <CardHeader className="gap-1">
                        <CardTitle className="text-lg">Assistance</CardTitle>
                        <CardDescription>
                            Records linked to this program (sortable columns).
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        <DataTable
                            columns={userProgramAssistanceColumns}
                            data={assistances.data}
                            emptyMessage="No assistance records for this program."
                            manualPagination
                        />
                        {assistances.total > 0 ? (
                            <div className="flex flex-col gap-3 border-t pt-4 sm:flex-row sm:items-center sm:justify-between">
                                <p className="text-sm text-muted-foreground">
                                    Showing {assistances.from} to {assistances.to}{' '}
                                    of {assistances.total} records
                                </p>
                                <div className="flex items-center gap-2">
                                    <Button
                                        variant="outline"
                                        size="sm"
                                        disabled={assistances.prev_page_url === null}
                                        asChild={assistances.prev_page_url !== null}
                                    >
                                        {assistances.prev_page_url ? (
                                            <Link
                                                href={assistances.prev_page_url}
                                                preserveScroll
                                            >
                                                Previous
                                            </Link>
                                        ) : (
                                            <span>Previous</span>
                                        )}
                                    </Button>
                                    <span className="text-sm text-muted-foreground">
                                        Page {assistances.current_page} of{' '}
                                        {assistances.last_page}
                                    </span>
                                    <Button
                                        variant="outline"
                                        size="sm"
                                        disabled={assistances.next_page_url === null}
                                        asChild={assistances.next_page_url !== null}
                                    >
                                        {assistances.next_page_url ? (
                                            <Link
                                                href={assistances.next_page_url}
                                                preserveScroll
                                            >
                                                Next
                                            </Link>
                                        ) : (
                                            <span>Next</span>
                                        )}
                                    </Button>
                                </div>
                            </div>
                        ) : null}
                    </CardContent>
                </Card>
            </div>
        </>
    );
}
