import { DataTableFacetedFilter } from '@/components/data-table/data-table-faceted-filter';
import type { ServerPaginationMeta } from '@/components/data-table/types';
import { ServerPagination } from '@/components/server-pagination';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import {
    ProgramFolderCard,
    type ProgramListRow,
} from '@/pages/user/programs/program-folder-card';
import {
    index as executiveProgramsIndex,
    show as executiveProgramShow,
} from '@/routes/executive/programs';
import type { BreadcrumbItem } from '@/types';
import { cn } from '@/lib/utils';
import { Head, Link, router, setLayoutProps } from '@inertiajs/react';
import { X } from 'lucide-react';
import { useCallback, useEffect, useState } from 'react';

type ExecutiveProgramRow = ProgramListRow & {
    approval_status: string;
    approval_label: string;
    department: { id: number; name: string; slug: string } | null;
};

type PaginatedPrograms = ServerPaginationMeta & {
    data: ExecutiveProgramRow[];
};

type DepartmentOption = {
    id: number;
    name: string;
};

type ProgramListFilters = {
    search: string;
    type: string[];
    status: string[];
    approval: string[];
    department: string[];
    page?: number;
    per_page?: number;
};

const programTypeOptions = [
    { label: 'Individual', value: 'individual' },
    { label: 'Organization', value: 'organization' },
] as const;

const programStatusOptions = [
    { label: 'Open', value: 'open' },
    { label: 'Closed', value: 'closed' },
] as const;

const approvalOptions = [
    { label: 'Awaiting executive', value: 'awaiting_governor' },
    { label: 'Draft', value: 'draft' },
    { label: 'Returned', value: 'returned' },
    { label: 'Approved', value: 'approved' },
] as const;

function buildProgramsQuery(
    filters: ProgramListFilters,
): Record<string, string | string[]> {
    const query: Record<string, string | string[]> = {};
    const search = filters.search.trim();

    if (search !== '') {
        query.search = search;
    }

    if (filters.type.length > 0) {
        query.type = filters.type;
    }

    if (filters.status.length > 0) {
        query.status = filters.status;
    }

    if (filters.approval.length > 0) {
        query.approval = filters.approval;
    }

    if (filters.department.length > 0) {
        query.department = filters.department;
    }

    if (filters.page !== undefined && filters.page > 1) {
        query.page = String(filters.page);
    }

    if (filters.per_page !== undefined && filters.per_page !== 12) {
        query.per_page = String(filters.per_page);
    }

    return query;
}

export default function GovernorProgramsIndex({
    programs,
    search: initialSearch,
    type: initialType,
    status: initialStatus,
    approval: initialApproval,
    department: initialDepartment,
    departments,
    per_page: initialPerPage,
    awaiting_count,
}: {
    programs: PaginatedPrograms;
    search: string;
    type: string[];
    status: string[];
    approval: string[];
    department: string[];
    departments: DepartmentOption[];
    per_page: number;
    awaiting_count: number;
}) {
    const [searchQuery, setSearchQuery] = useState(initialSearch);

    useEffect(() => {
        setLayoutProps({
            breadcrumbs: [
                {
                    title: 'Executive',
                    href: executiveProgramsIndex(),
                },
            ] satisfies BreadcrumbItem[],
        });
    }, []);

    useEffect(() => {
        setSearchQuery(initialSearch);
    }, [initialSearch]);

    const navigateWithFilters = useCallback(
        (overrides: Partial<ProgramListFilters> = {}) => {
            const next: ProgramListFilters = {
                search: overrides.search ?? searchQuery,
                type: overrides.type ?? initialType,
                status: overrides.status ?? initialStatus,
                approval: overrides.approval ?? initialApproval,
                department: overrides.department ?? initialDepartment,
                page: overrides.page ?? 1,
                per_page: overrides.per_page ?? initialPerPage,
            };

            router.get(
                executiveProgramsIndex.url({ query: buildProgramsQuery(next) }),
                {},
                {
                    preserveState: true,
                    replace: true,
                    only: [
                        'programs',
                        'search',
                        'type',
                        'status',
                        'approval',
                        'department',
                        'per_page',
                        'awaiting_count',
                    ],
                },
            );
        },
        [
            searchQuery,
            initialType,
            initialStatus,
            initialApproval,
            initialDepartment,
            initialPerPage,
        ],
    );

    useEffect(() => {
        const trimmed = searchQuery.trim();

        if (trimmed === initialSearch.trim()) {
            return;
        }

        const handle = window.setTimeout(() => {
            navigateWithFilters({ search: trimmed });
        }, 400);

        return () => window.clearTimeout(handle);
    }, [searchQuery, initialSearch, navigateWithFilters]);

    const isFiltered =
        initialSearch.trim() !== '' ||
        initialType.length > 0 ||
        initialStatus.length > 0 ||
        initialApproval.length > 0 ||
        initialDepartment.length > 0;

    return (
        <>
            <Head title="Executive" />
            <div className="flex h-full min-w-0 flex-1 flex-col gap-4 overflow-x-hidden rounded-xl p-4">
                <div className="flex flex-col gap-2 sm:flex-row sm:items-end sm:justify-between">
                    <div>
                        <h1 className="text-2xl font-semibold tracking-tight">
                            Programs
                        </h1>
                        <p className="text-sm text-muted-foreground">
                            {awaiting_count} awaiting executive approval
                        </p>
                    </div>
                </div>
                <div className="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                    <div className="flex flex-1 flex-col gap-2 sm:flex-row sm:flex-wrap sm:items-center">
                        <Input
                            type="text"
                            name="search"
                            autoComplete="off"
                            placeholder="Search by program name"
                            value={searchQuery}
                            onChange={(event) =>
                                setSearchQuery(event.target.value)
                            }
                            className={cn(
                                'max-w-md',
                                searchQuery.trim().length > 0 &&
                                    'border-primary bg-primary/5 ring-1 ring-primary/30',
                            )}
                        />
                        <DataTableFacetedFilter
                            filterValue={initialType}
                            title="Type"
                            options={[...programTypeOptions]}
                            onFilterChange={(values) =>
                                navigateWithFilters({ type: values })
                            }
                        />
                        <DataTableFacetedFilter
                            filterValue={initialStatus}
                            title="Status"
                            options={[...programStatusOptions]}
                            onFilterChange={(values) =>
                                navigateWithFilters({ status: values })
                            }
                        />
                        <DataTableFacetedFilter
                            filterValue={initialApproval}
                            title="Approval"
                            options={[...approvalOptions]}
                            onFilterChange={(values) =>
                                navigateWithFilters({ approval: values })
                            }
                        />
                        <DataTableFacetedFilter
                            filterValue={initialDepartment}
                            title="Department"
                            options={departments.map((department) => ({
                                label: department.name,
                                value: String(department.id),
                            }))}
                            onFilterChange={(values) =>
                                navigateWithFilters({ department: values })
                            }
                        />
                        {isFiltered ? (
                            <Button
                                type="button"
                                variant="ghost"
                                className="h-8 px-2 lg:px-3"
                                onClick={() => {
                                    setSearchQuery('');
                                    navigateWithFilters({
                                        search: '',
                                        type: [],
                                        status: [],
                                        approval: [],
                                        department: [],
                                    });
                                }}
                            >
                                Reset
                                <X className="ml-2 size-4" />
                            </Button>
                        ) : null}
                    </div>
                </div>
                <div>
                    {programs.data.length === 0 ? (
                        <div className="flex flex-col items-start gap-1 border-t border-foreground/10 py-12">
                            <p className="text-sm font-medium">
                                No programs found
                            </p>
                            <p className="text-sm text-muted-foreground">
                                Nothing matches your filters.
                            </p>
                        </div>
                    ) : (
                        <div className="flex flex-col gap-4">
                            <div className="grid auto-rows-min gap-x-5 gap-y-7 md:grid-cols-2 lg:grid-cols-4">
                                {programs.data.map((program) => (
                                    <Link
                                        key={program.id}
                                        href={executiveProgramShow.url(
                                            program.id,
                                        )}
                                        prefetch
                                        className="block h-full rounded-xl outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2"
                                    >
                                        <ProgramFolderCard
                                            program={program}
                                            showDepartment
                                        />
                                    </Link>
                                ))}
                            </div>

                            <ServerPagination
                                pagination={programs}
                                onPageChange={(page) =>
                                    navigateWithFilters({ page })
                                }
                                onPerPageChange={(perPage) =>
                                    navigateWithFilters({
                                        per_page: perPage,
                                        page: 1,
                                    })
                                }
                            />
                        </div>
                    )}
                </div>
            </div>
        </>
    );
}
