import { Head, router, setLayoutProps } from '@inertiajs/react';
import { Check, ClipboardCheck, Undo2, X } from 'lucide-react';
import { useCallback, useEffect, useState } from 'react';
import { DataTableFacetedFilter } from '@/components/data-table/data-table-faceted-filter';
import type { ServerPaginationMeta } from '@/components/data-table/types';
import { ServerPagination } from '@/components/server-pagination';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { NoticeBanner } from '@/components/user/notice-banner';
import { ProgramAnalyticsDialog } from '@/components/user/program-analytics-dialog';
import { ProgramCard } from '@/components/user/program-card';
import type { ProgramCardMetric } from '@/components/user/program-card';
import { ProgramDecisionDialog } from '@/components/user/programs/program-decision-dialog';
import type { ProgramDecisionAction } from '@/components/user/programs/program-decision-dialog';
import { StatusPillTabs } from '@/components/user/status-pill-tabs';
import { cn } from '@/lib/utils';
import type { ProgramListRow } from '@/pages/user/programs/program-folder-card';
import {
    analytics as executiveProgramAnalytics,
    approve as approveProgram,
    index as executiveProgramsIndex,
    returnMethod as returnProgram,
    show as executiveProgramShow,
} from '@/routes/executive/programs';
import { exportMethod as exportAssistances } from '@/routes/user/programs/assistances';
import type { BreadcrumbItem } from '@/types';

type ApprovalCounts = {
    all: number;
    draft: number;
    awaiting_governor: number;
    approved: number;
    returned: number;
};

type ExecutiveProgramRow = ProgramListRow & {
    approval_status: string;
    approval_label: string;
    can_approve?: boolean;
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
    approval_counts: approvalCounts,
    program_metrics: programMetrics = {},
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
    approval_counts: ApprovalCounts;
    program_metrics?: Record<string, ProgramCardMetric>;
}) {
    const [searchQuery, setSearchQuery] = useState(initialSearch);
    const [analyticsProgram, setAnalyticsProgram] =
        useState<ExecutiveProgramRow | null>(null);
    const [decision, setDecision] = useState<{
        program: ExecutiveProgramRow;
        action: Extract<ProgramDecisionAction, 'approve' | 'return'>;
    } | null>(null);

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
                        'approval_counts',
                        'program_metrics',
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
    const selectedApproval = initialApproval[0] ?? 'all';

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
                {awaiting_count > 0 ? (
                    <NoticeBanner
                        tone="amber"
                        icon={ClipboardCheck}
                        title="Programs awaiting executive approval"
                        description={`${awaiting_count} endorsed ${awaiting_count === 1 ? 'program is' : 'programs are'} waiting for a decision.`}
                        action={
                            <Button
                                type="button"
                                size="sm"
                                onClick={() =>
                                    navigateWithFilters({
                                        approval: ['awaiting_governor'],
                                    })
                                }
                            >
                                Review queue ({awaiting_count})
                            </Button>
                        }
                    />
                ) : null}
                <div data-tour="programs-approval">
                    <StatusPillTabs
                        value={selectedApproval}
                        onChange={(value) =>
                            navigateWithFilters({
                                approval: value === 'all' ? [] : [value],
                            })
                        }
                        pills={[
                            {
                                value: 'all',
                                label: 'All Programs',
                                count: approvalCounts.all,
                            },
                            {
                                value: 'approved',
                                label: 'Approved & Active',
                                count: approvalCounts.approved,
                                dotClassName: 'bg-emerald-500',
                            },
                            {
                                value: 'awaiting_governor',
                                label: 'Awaiting Governor Approval',
                                count: approvalCounts.awaiting_governor,
                                dotClassName: 'bg-amber-500',
                            },
                            {
                                value: 'draft',
                                label: 'Awaiting Dept Review',
                                count: approvalCounts.draft,
                                dotClassName: 'bg-blue-500',
                            },
                            {
                                value: 'returned',
                                label: 'Returned / Shelved',
                                count: approvalCounts.returned,
                                dotClassName: 'bg-muted-foreground',
                            },
                        ]}
                    />
                </div>
                <div className="flex flex-col gap-2 sm:flex-row sm:flex-wrap sm:items-center">
                    <Input
                        type="text"
                        name="search"
                        autoComplete="off"
                        placeholder="Search by program name"
                        value={searchQuery}
                        onChange={(event) => setSearchQuery(event.target.value)}
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
                            <div className="grid items-stretch gap-4 md:grid-cols-2 xl:grid-cols-3">
                                {programs.data.map((program) => (
                                    <ProgramCard
                                        key={program.id}
                                        program={program}
                                        metrics={
                                            programMetrics[String(program.id)]
                                        }
                                        href={executiveProgramShow.url(
                                            program.id,
                                        )}
                                        onInspect={() =>
                                            setAnalyticsProgram(program)
                                        }
                                        actions={
                                            program.can_approve ? (
                                                <div className="flex gap-2">
                                                    <Button
                                                        type="button"
                                                        className="flex-1"
                                                        onClick={() =>
                                                            setDecision({
                                                                program,
                                                                action: 'approve',
                                                            })
                                                        }
                                                    >
                                                        <Check data-icon="inline-start" />
                                                        Approve
                                                    </Button>
                                                    <Button
                                                        type="button"
                                                        variant="outline"
                                                        className="flex-1"
                                                        onClick={() =>
                                                            setDecision({
                                                                program,
                                                                action: 'return',
                                                            })
                                                        }
                                                    >
                                                        <Undo2 data-icon="inline-start" />
                                                        Return
                                                    </Button>
                                                </div>
                                            ) : null
                                        }
                                    />
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
            <ProgramDecisionDialog
                open={decision !== null}
                onOpenChange={(open) => {
                    if (!open) {
                        setDecision(null);
                    }
                }}
                programName={decision?.program.name ?? ''}
                action={decision?.action ?? 'approve'}
                url={
                    decision === null
                        ? ''
                        : decision.action === 'approve'
                          ? approveProgram.url(decision.program.id)
                          : returnProgram.url(decision.program.id)
                }
            />
            <ProgramAnalyticsDialog
                program={analyticsProgram}
                open={analyticsProgram !== null}
                onOpenChange={(open) => {
                    if (!open) {
                        setAnalyticsProgram(null);
                    }
                }}
                url={
                    analyticsProgram
                        ? executiveProgramAnalytics.url(analyticsProgram.id)
                        : null
                }
                exportUrl={
                    analyticsProgram?.department?.slug
                        ? exportAssistances.url({
                              department: analyticsProgram.department.slug,
                              program: analyticsProgram.id,
                          })
                        : null
                }
            />
        </>
    );
}
