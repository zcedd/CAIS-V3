import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { dashboard } from '@/routes';
import {
    index as departmentProgramsIndex,
    show as departmentProgramShow,
} from '@/routes/user/programs';
import type { BreadcrumbItem } from '@/types';
import { Head, Link, router, setLayoutProps } from '@inertiajs/react';
import { useEffect, useState } from 'react';

type DepartmentSummary = {
    id: number;
    name: string;
    slug: string;
};

type ProgramRow = {
    id: number;
    name: string;
    descriptions: string | null;
    start_at: string | null;
    end_at: string | null;
    is_closed: boolean | null;
    is_organization: boolean | null;
    department?: DepartmentSummary | null;
};

export default function UserProgramsIndex({
    programs,
    department,
    search: initialSearch,
}: {
    programs: ProgramRow[];
    department: DepartmentSummary | null;
    search: string;
}) {
    const [searchQuery, setSearchQuery] = useState(initialSearch);

    useEffect(() => {
        setSearchQuery(initialSearch);
    }, [initialSearch]);

    if (department?.slug) {
        const programsHref = departmentProgramsIndex.url(department.slug);
        setLayoutProps({
            breadcrumbs: [
                {
                    title: 'Programs',
                    href: programsHref,
                },
            ] satisfies BreadcrumbItem[],
        });
    }

    useEffect(() => {
        const trimmed = searchQuery.trim();
        if (trimmed === initialSearch.trim() || !department?.slug) {
            return;
        }

        const handle = window.setTimeout(() => {
            router.get(
                departmentProgramsIndex.url(
                    { department: department.slug },
                    trimmed === '' ? undefined : { query: { search: trimmed } },
                ),
                {},
                {
                    preserveState: true,
                    replace: true,
                    only: ['programs', 'search', 'department'],
                },
            );
        }, 400);

        return () => window.clearTimeout(handle);
    }, [searchQuery, initialSearch, department?.slug]);

    const heading = department ? `${department.name} programs` : 'Programs';

    return (
        <>
            <Head title="Department programs" />
            <div className="flex h-full flex-1 flex-col gap-4 overflow-x-auto rounded-xl p-4">
                <div className="flex flex-col gap-2 sm:flex-row sm:items-end sm:justify-between">
                    <div>
                        <h1 className="text-2xl font-semibold tracking-tight">
                            {heading}
                        </h1>
                        <p className="text-sm text-muted-foreground">
                            {department
                                ? 'Programs assigned to your department.'
                                : 'You are not linked to a department yet, so no programs are shown.'}
                        </p>
                    </div>
                </div>
                <div className="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                    <Input
                        type="search"
                        name="search"
                        placeholder="Search by program name"
                        value={searchQuery}
                        onChange={(e) => setSearchQuery(e.target.value)}
                        className="max-w-md"
                    />
                    <Button variant="outline" asChild>
                        <Link href={dashboard().url}>Create program</Link>
                    </Button>
                </div>
                {programs.length === 0 ? (
                    <p className="text-sm text-muted-foreground">
                        No programs match your filters.
                    </p>
                ) : (
                    <div className="grid auto-rows-min gap-4 md:grid-cols-2 lg:grid-cols-3">
                        {programs.map((program) => {
                            const card = (
                                <Card className="flex h-full flex-col bg-card transition-colors hover:border-primary/50 hover:shadow-sm">
                                    <CardHeader className="gap-1">
                                        <CardTitle className="text-lg">
                                            {program.name}
                                        </CardTitle>
                                        <CardDescription>
                                            <span className="font-medium text-foreground">
                                                {program.is_organization
                                                    ? 'Organization'
                                                    : 'Personal'}
                                            </span>
                                            {program.is_closed ? (
                                                <Badge variant="destructive">
                                                    Closed
                                                </Badge>
                                            ) : (
                                                <Badge variant="default">
                                                    Open
                                                </Badge>
                                            )}
                                        </CardDescription>
                                    </CardHeader>
                                    <CardContent className="flex flex-1 flex-col gap-2 text-sm text-muted-foreground">
                                        <p className="line-clamp-4">
                                            {program.descriptions}
                                        </p>
                                        <p>
                                            <span className="font-medium text-foreground">
                                                Period:{' '}
                                            </span>
                                            {program.start_at ?? '—'}
                                            {program.end_at
                                                ? ` – ${program.end_at}`
                                                : ''}
                                        </p>
                                    </CardContent>
                                </Card>
                            );

                            return department?.slug ? (
                                <Link
                                    key={program.id}
                                    href={departmentProgramShow.url({
                                        department: department.slug,
                                        program: program.id,
                                    })}
                                    prefetch
                                    className="block h-full rounded-xl ring-offset-background outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2"
                                >
                                    {card}
                                </Link>
                            ) : (
                                <div key={program.id}>{card}</div>
                            );
                        })}
                    </div>
                )}
            </div>
        </>
    );
}
