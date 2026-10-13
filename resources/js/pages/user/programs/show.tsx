import { Head, router, setLayoutProps, WhenVisible } from '@inertiajs/react';
import {
    BarChart3,
    Building2,
    CalendarRange,
    Coins,
    Copy,
    Download,
    Package,
    Pencil,
    UserRound,
    Users,
} from 'lucide-react';
import {
    lazy,
    Suspense,
    useCallback,
    useEffect,
    useMemo,
    useRef,
    useState,
} from 'react';
import { toast } from 'sonner';
import { DataTableSkeleton } from '@/components/data-table/data-table-skeleton';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Progress } from '@/components/ui/progress';
import { Skeleton } from '@/components/ui/skeleton';
import { ApprovalBadge } from '@/components/user/approval-badge';
import { CodeChip } from '@/components/user/code-chip';
import { ProgramAnalyticsDialog } from '@/components/user/program-analytics-dialog';
import type { ProgramCardMetric } from '@/components/user/program-card';
import { ProgramApprovalSubmit } from '@/components/user/programs/program-approval-submit';
import { useCan } from '@/hooks/use-can';
import { usePageCommands } from '@/lib/command-registry';
import type { PageCommand } from '@/lib/command-registry';
import { formatPeso } from '@/lib/format-peso';
import {
    formatProgramDate,
    formatProgramPeriod,
} from '@/lib/format-program-period';
import { Permission } from '@/lib/permissions';
import { cn } from '@/lib/utils';
import type {
    AssistanceModeOption,
    AssistanceProgramItemOption,
    AssistanceRequestSubStatusOption,
    AssistanceSelectOption,
    AssistanceTableFilters,
    AssistanceTransferProgramOption,
    DepartmentStaffOption,
    WorkflowOption,
    ModeFilterOption,
    StatusFilterOption,
} from '@/pages/user/programs/assistance-toolbar';
import {
    ASSISTANCE_TABLE_DEFER_GROUP_PROPS,
    ASSISTANCE_TABLE_SKELETON_COLUMNS,
    buildTableQuery,
    isAssistancesPartialVisit,
    isAssistancesTableReady,
} from '@/pages/user/programs/program-assistance-table';
import type { PaginatedAssistances } from '@/pages/user/programs/program-assistance-table';
import {
    ProgramShowMetrics,
    ProgramShowMetricsSkeleton,
    ProgramReachCards,
    ProgramReachSkeleton,
} from '@/pages/user/programs/program-show-metrics';
import {
    ProgramStatusBreakdown,
    ProgramStatusBreakdownSkeleton,
} from '@/pages/user/programs/status-breakdown';
import { show as publicApplyShow } from '@/routes/public/apply';
import {
    analytics as programAnalytics,
    index as departmentProgramsIndex,
    show as departmentProgramShow,
} from '@/routes/user/programs';
import { exportMethod as exportAssistances } from '@/routes/user/programs/assistances';
import type { BreadcrumbItem } from '@/types';
import type {
    DocumentTypeOption,
    ProgramDocumentRequirementInput,
} from '@/types/document';
import type {
    ProgramCoveredItem,
    ProgramFund,
    ProgramStatusBreakdownPoint,
    ProgramStockRow,
    ProgramSummary,
} from '@/types/program';
import type {
    ProgramFieldDefinition,
    ProgramFieldOption,
} from '@/types/program-field';

const ProgramAssistanceTableSection = lazy(() =>
    import('@/pages/user/programs/program-assistance-table').then((module) => ({
        default: module.ProgramAssistanceTableSection,
    })),
);

const ProgramEditDrawer = lazy(() =>
    import('@/components/user/programs/program-edit-drawer').then((module) => ({
        default: module.ProgramEditDrawer,
    })),
);

type DepartmentSummary = {
    id: number;
    name: string;
    slug: string;
};

type SelectOption = {
    id: number;
    name: string;
    unit: string;
    year: string;
};

type ProgramDetail = {
    id: number;
    name: string;
    descriptions: string | null;
    start_at: string | null;
    end_at: string | null;
    start_at_input: string | null;
    end_at_input: string | null;
    is_closed: boolean | null;
    is_organization: boolean | null;
    public_intake?: boolean | null;
    distribution_mode?: 'individual' | 'batch' | 'both' | null;
    kind?: string | null;
    approval_status?: string | null;
    approval_label?: string | null;
    can_submit_approval?: boolean;
    batch_name?: string | null;
    department_id: number;
    parent?: { id: number; name: string } | null;
};

type ProgramEditRelations = {
    fund_ids: number[];
    item_ids: number[];
    fields: ProgramFieldDefinition[];
    document_requirements?: ProgramDocumentRequirementInput[];
    workflow_id?: number | null;
};

const MS_PER_DAY = 86_400_000;

function formatFundAmount(amount: number | null): string | null {
    if (amount === null) {
        return null;
    }

    return amount.toLocaleString('en-PH', {
        style: 'currency',
        currency: 'PHP',
        maximumFractionDigits: 2,
    });
}

type ProgramTimelineInfo = {
    percent: number;
    caption: string;
};

function programTimelineInfo(
    startInput: string | null,
    endInput: string | null,
): ProgramTimelineInfo | null {
    if (!startInput || !endInput) {
        return null;
    }

    const start = new Date(startInput).getTime();
    const end = new Date(endInput).getTime();

    if (Number.isNaN(start) || Number.isNaN(end) || end <= start) {
        return null;
    }

    const now = Date.now();
    const percent = Math.min(
        100,
        Math.max(0, Math.round(((now - start) / (end - start)) * 100)),
    );

    if (now < start) {
        const days = Math.ceil((start - now) / MS_PER_DAY);

        return {
            percent: 0,
            caption: `Starts in ${days} ${days === 1 ? 'day' : 'days'}`,
        };
    }

    if (now > end) {
        const days = Math.floor((now - end) / MS_PER_DAY);

        return {
            percent: 100,
            caption:
                days === 0
                    ? 'Ended today'
                    : `Ended ${days} ${days === 1 ? 'day' : 'days'} ago`,
        };
    }

    const daysLeft = Math.ceil((end - now) / MS_PER_DAY);

    return {
        percent,
        caption: `${percent}% elapsed · ${daysLeft} ${daysLeft === 1 ? 'day' : 'days'} remaining`,
    };
}

type AssistanceTableSnapshot = {
    assistances: PaginatedAssistances;
    mode_options: ModeFilterOption[];
    status_options: StatusFilterOption[];
    mode_of_request_options: AssistanceModeOption[];
    program_items: AssistanceProgramItemOption[];
    program_fields: ProgramFieldOption[];
    request_sub_status_options: AssistanceRequestSubStatusOption[];
    staff_options: DepartmentStaffOption[];
    transfer_program_options: AssistanceTransferProgramOption[];
};

function OverviewDetailSkeleton() {
    return (
        <div className="space-y-2" aria-busy="true">
            <Skeleton className="h-3 w-32 rounded-sm" />
            <Skeleton className="h-3 w-40 rounded-sm" />
        </div>
    );
}

export default function UserProgramShow({
    program,
    summary,
    status_breakdown,
    program_metrics,
    program_funds,
    program_covered_items,
    program_stock,
    department,
    program_edit,
    funds,
    items,
    assistances,
    sort,
    direction,
    per_page,
    search,
    status,
    mode,
    mode_options,
    status_options,
    mode_of_request_options,
    program_items,
    program_fields,
    request_sub_status_options,
    transfer_program_options,
    staff_options,
    document_types = [],
    document_requirements = [],
    workflow_options = [],
}: {
    program: ProgramDetail;
    summary?: ProgramSummary;
    status_breakdown?: ProgramStatusBreakdownPoint[];
    program_metrics?: ProgramCardMetric | null;
    program_funds?: ProgramFund[];
    program_covered_items?: ProgramCoveredItem[];
    program_stock?: ProgramStockRow[];
    department: DepartmentSummary | null;
    program_edit?: ProgramEditRelations;
    funds?: SelectOption[];
    items?: SelectOption[];
    assistances?: PaginatedAssistances;
    sort: string;
    direction: 'asc' | 'desc';
    per_page: number;
    search: string;
    status: string[];
    mode: string[];
    mode_options?: ModeFilterOption[];
    status_options?: StatusFilterOption[];
    mode_of_request_options?: AssistanceModeOption[];
    organization_options?: AssistanceSelectOption[];
    program_items?: AssistanceProgramItemOption[];
    program_fields?: ProgramFieldOption[];
    request_sub_status_options?: AssistanceRequestSubStatusOption[];
    staff_options?: DepartmentStaffOption[];
    transfer_program_options?: AssistanceTransferProgramOption[];
    document_types?: DocumentTypeOption[];
    document_requirements?: ProgramDocumentRequirementInput[];
    workflow_options?: WorkflowOption[];
}) {
    const [editOpen, setEditOpen] = useState(false);
    const [analyticsOpen, setAnalyticsOpen] = useState(false);
    const [editFormKey, setEditFormKey] = useState(0);
    const [tableState, setTableState] = useState({
        sort,
        direction,
        per_page,
        search,
        status,
        mode,
    });
    const [isTableReloading, setIsTableReloading] = useState(false);
    const [assistanceTableSnapshot, setAssistanceTableSnapshot] = useState<{
        programId: number;
        table: AssistanceTableSnapshot;
    } | null>(null);

    const tableStateRef = useRef(tableState);

    useEffect(() => {
        tableStateRef.current = tableState;
    }, [tableState]);

    useEffect(() => {
        const liveAssistanceTable = {
            assistances,
            mode_options,
            status_options,
            mode_of_request_options,
            program_items,
            program_fields,
            request_sub_status_options,
            staff_options,
            transfer_program_options,
        };

        if (isAssistancesTableReady(liveAssistanceTable)) {
            setAssistanceTableSnapshot({
                programId: program.id,
                table: liveAssistanceTable,
            });
        }
    }, [
        program.id,
        assistances,
        mode_options,
        status_options,
        mode_of_request_options,
        program_items,
        program_fields,
        request_sub_status_options,
        staff_options,
        transfer_program_options,
    ]);

    useEffect(() => {
        if (
            !editOpen ||
            (funds !== undefined &&
                items !== undefined &&
                program_edit !== undefined)
        ) {
            return;
        }

        router.reload({
            only: ['funds', 'items', 'program_edit'],
        });
    }, [editOpen, funds, items, program_edit]);

    useEffect(() => {
        if (!editOpen) {
            return;
        }

        setEditFormKey((key) => key + 1);
    }, [editOpen]);

    useEffect(() => {
        const removeStart = router.on('start', (event) => {
            if (isAssistancesPartialVisit(event.detail.visit.only)) {
                setIsTableReloading(true);
            }
        });

        const removeFinish = router.on('finish', () => {
            setIsTableReloading(false);
        });

        return () => {
            removeStart();
            removeFinish();
        };
    }, []);

    const tableFilters: AssistanceTableFilters = {
        search: tableState.search,
        status: tableState.status,
        mode: tableState.mode,
    };

    useEffect(() => {
        if (!department?.slug) {
            return;
        }

        const programsHref = departmentProgramsIndex.url(department.slug);
        const selfHref = departmentProgramShow.url({
            department: department.slug,
            program: program.id,
        });

        const breadcrumbs: BreadcrumbItem[] = [
            {
                title: 'Programs',
                href: programsHref,
            },
        ];

        if (program.parent) {
            breadcrumbs.push({
                title: program.parent.name,
                href: departmentProgramShow.url({
                    department: department.slug,
                    program: program.parent.id,
                }),
            });
        }

        breadcrumbs.push({
            title: program.batch_name ?? program.name,
            href: selfHref,
        });

        setLayoutProps({
            breadcrumbs,
        });
    }, [
        department?.slug,
        program.id,
        program.name,
        program.batch_name,
        program.parent,
    ]);

    const visitTable = useCallback(
        (
            overrides: Partial<
                AssistanceTableFilters & {
                    sort: string;
                    direction: 'asc' | 'desc';
                    per_page: number;
                    page: number;
                }
            > = {},
        ) => {
            if (!department?.slug) {
                return;
            }

            const next = { ...tableStateRef.current, ...overrides };
            setTableState(next);
            router.cancelAll();
            router.get(
                departmentProgramShow.url(
                    { department: department.slug, program: program.id },
                    {
                        query: buildTableQuery(next, overrides),
                    },
                ),
                {},
                {
                    preserveState: true,
                    preserveScroll: true,
                    only: ['assistances'],
                },
            );
        },
        [department?.slug, program.id],
    );

    const tableSkeleton = (
        <DataTableSkeleton
            columnCount={ASSISTANCE_TABLE_SKELETON_COLUMNS}
            rowCount={tableState.per_page}
        />
    );

    const heading = program.name;
    const programCode = program.batch_name?.trim()
        ? program.batch_name
        : `PRG-${String(program.id).padStart(4, '0')}`;
    const fundTotal = (program_funds ?? []).reduce(
        (sum, fund) => sum + (fund.amount ?? 0),
        0,
    );
    const { can } = useCan();
    const canEdit = Boolean(department?.slug) && can(Permission.ProgramUpdate);
    const canExportAssistance = can(Permission.ProgramDownloadAssistance);
    const pageCommands = useMemo(() => {
        const commands: PageCommand[] = [];

        if (canEdit) {
            commands.push({
                id: 'program.edit',
                label: 'Edit program',
                icon: Pencil,
                onSelect: () => setEditOpen(true),
            });
        }

        commands.push({
            id: 'program.analytics',
            label: 'Inspect analytics',
            icon: BarChart3,
            onSelect: () => setAnalyticsOpen(true),
        });

        if (canExportAssistance && department?.slug) {
            const exportUrl = exportAssistances.url({
                department: department.slug,
                program: program.id,
            });

            commands.push({
                id: 'program.export',
                label: 'Export requests',
                icon: Download,
                onSelect: () => {
                    window.location.assign(exportUrl);
                },
            });
        }

        commands.push({
            id: 'program.copy-link',
            label: 'Copy program link',
            icon: Copy,
            onSelect: () => {
                void navigator.clipboard
                    .writeText(window.location.href)
                    .then(() => toast.success('Program link copied'));
            },
        });

        return commands;
    }, [canEdit, canExportAssistance, department, program.id]);
    usePageCommands(pageCommands);
    const canCreateAssistance = Boolean(
        department?.slug &&
        !program.is_closed &&
        can(Permission.AssistanceCreate),
    );
    const liveAssistanceTable = {
        assistances,
        mode_options,
        status_options,
        mode_of_request_options,
        program_items,
        program_fields,
        request_sub_status_options,
        staff_options,
        transfer_program_options,
    };
    const resolvedAssistanceTable = isAssistancesTableReady(liveAssistanceTable)
        ? liveAssistanceTable
        : assistanceTableSnapshot?.programId === program.id
          ? assistanceTableSnapshot.table
          : null;
    const canTransferAssistance = Boolean(
        department?.slug &&
        !program.is_closed &&
        can(Permission.AssistanceUpdate) &&
        (resolvedAssistanceTable?.transfer_program_options.length ?? 0) > 0,
    );
    const isClosed = Boolean(program.is_closed);
    const description = program.descriptions?.trim();
    const timeline = programTimelineInfo(
        program.start_at_input,
        program.end_at_input,
    );

    const closeEditDrawer = useCallback(() => {
        setEditOpen(false);
    }, []);

    return (
        <>
            <Head title={heading} />
            <div className="flex h-full min-w-0 flex-1 flex-col gap-4 overflow-x-hidden rounded-xl p-4">
                <div className="flex flex-col gap-2 sm:flex-row sm:items-end sm:justify-between">
                    <div data-tour="program-header" className="space-y-2">
                        <h1 className="text-2xl font-semibold tracking-tight">
                            {heading}
                        </h1>
                        <div className="flex flex-wrap items-center gap-1.5">
                            <CodeChip>{programCode}</CodeChip>
                            <ApprovalBadge status={program.approval_status} />
                            <Badge
                                variant="outline"
                                className={cn(
                                    isClosed
                                        ? 'border-border text-muted-foreground'
                                        : 'border-emerald-600/30 bg-emerald-600/10 text-emerald-700 dark:text-emerald-400',
                                )}
                            >
                                {isClosed ? 'Closed' : 'Open'}
                            </Badge>
                            <Badge variant="outline">
                                {program.distribution_mode === 'individual'
                                    ? 'Individual distribution'
                                    : program.distribution_mode === 'both'
                                      ? 'Individual and batch'
                                      : 'Batch distribution'}
                            </Badge>
                            <Badge variant="outline">
                                {program.is_organization ? (
                                    <Users aria-hidden />
                                ) : (
                                    <UserRound aria-hidden />
                                )}
                                {program.is_organization
                                    ? 'Organization'
                                    : 'Individual'}
                            </Badge>
                            {department ? (
                                <Badge variant="outline">
                                    <Building2 aria-hidden />
                                    {department.name}
                                </Badge>
                            ) : null}
                            {program.public_intake && !isClosed ? (
                                <Badge variant="outline">Public intake</Badge>
                            ) : null}
                        </div>
                    </div>
                    <div className="flex flex-wrap gap-2">
                        {program.public_intake && !isClosed ? (
                            <Button
                                type="button"
                                variant="outline"
                                onClick={() => {
                                    void navigator.clipboard.writeText(
                                        `${window.location.origin}${publicApplyShow.url(program.id)}`,
                                    );
                                    toast.success('Public intake URL copied.');
                                }}
                            >
                                <Copy className="size-4" />
                                Copy public URL
                            </Button>
                        ) : null}
                        <Button
                            type="button"
                            variant="outline"
                            onClick={() => setAnalyticsOpen(true)}
                        >
                            <BarChart3 className="size-4" />
                            Inspect analytics
                        </Button>
                        {department?.slug ? (
                            <ProgramApprovalSubmit
                                departmentSlug={department.slug}
                                programId={program.id}
                                programName={program.name}
                                canSubmit={program.can_submit_approval}
                            />
                        ) : null}
                        {canEdit ? (
                            <Button
                                type="button"
                                data-tour="program-edit"
                                onClick={() => setEditOpen(true)}
                            >
                                <Pencil className="size-4" />
                                Edit program
                            </Button>
                        ) : null}
                    </div>
                </div>

                <div data-tour="program-kpis">
                    <WhenVisible
                        data={['summary', 'program_metrics']}
                        buffer={200}
                        fallback={<ProgramShowMetricsSkeleton />}
                    >
                        {program_metrics ? (
                            <ProgramShowMetrics
                                metrics={program_metrics}
                                summary={summary}
                            />
                        ) : (
                            <ProgramShowMetricsSkeleton />
                        )}
                    </WhenVisible>
                </div>

                <div data-tour="program-requests-status-chart">
                    <WhenVisible
                        data={['program_metrics', 'status_breakdown']}
                        buffer={200}
                        fallback={
                            <div className="grid gap-4 xl:grid-cols-3">
                                <ProgramReachSkeleton />
                                <ProgramStatusBreakdownSkeleton />
                            </div>
                        }
                    >
                        <div className="grid gap-4 xl:grid-cols-3">
                            {program_metrics ? (
                                <ProgramReachCards metrics={program_metrics} />
                            ) : (
                                <ProgramReachSkeleton />
                            )}
                            {status_breakdown ? (
                                <ProgramStatusBreakdown
                                    breakdown={status_breakdown}
                                />
                            ) : (
                                <ProgramStatusBreakdownSkeleton />
                            )}
                        </div>
                    </WhenVisible>
                </div>

                <div
                    data-tour="program-overview"
                    className="grid gap-4 lg:grid-cols-3"
                >
                    <section className="flex flex-col gap-4 rounded-xl border border-border bg-card p-4">
                        <h2 className="text-[15px] font-semibold tracking-tight">
                            Description
                        </h2>
                        <p
                            className={cn(
                                'text-sm leading-relaxed whitespace-pre-wrap',
                                description
                                    ? 'text-muted-foreground'
                                    : 'text-muted-foreground/60 italic',
                            )}
                        >
                            {description || 'No description'}
                        </p>
                        <div className="space-y-2">
                            <p className="flex items-center gap-1.5 text-xs font-medium tracking-wide text-muted-foreground uppercase">
                                <CalendarRange className="size-3.5" />
                                Program period
                            </p>
                            <p className="text-sm tabular-nums">
                                {formatProgramPeriod(
                                    program.start_at,
                                    program.end_at,
                                )}
                            </p>
                            {timeline ? (
                                <div className="space-y-1.5">
                                    <Progress
                                        value={timeline.percent}
                                        aria-label={`Program timeline ${timeline.percent}% elapsed`}
                                    />
                                    <p className="text-xs text-muted-foreground tabular-nums">
                                        {timeline.caption}
                                    </p>
                                </div>
                            ) : program.start_at_input &&
                              !program.end_at_input ? (
                                <p className="text-xs text-muted-foreground">
                                    Ongoing since{' '}
                                    {formatProgramDate(program.start_at_input)}{' '}
                                    (no end date set)
                                </p>
                            ) : null}
                        </div>
                    </section>

                    <section className="flex flex-col gap-3 rounded-xl border border-border bg-card p-4">
                        <div className="flex items-center justify-between gap-2">
                            <h2 className="flex items-center gap-1.5 text-[15px] font-semibold tracking-tight">
                                <Coins className="size-3.5" />
                                Funding sources
                            </h2>
                            {program_funds && program_funds.length > 0 ? (
                                <span className="font-mono text-xs tabular-nums">
                                    {formatPeso(fundTotal)}
                                </span>
                            ) : null}
                        </div>
                        {program_funds === undefined ? (
                            <OverviewDetailSkeleton />
                        ) : program_funds.length === 0 ? (
                            <p className="text-sm text-muted-foreground/60 italic">
                                No funding sources linked
                            </p>
                        ) : (
                            <ul className="space-y-1.5">
                                {program_funds.map((fund) => (
                                    <li
                                        key={fund.id}
                                        className="flex items-baseline justify-between gap-2 text-sm"
                                    >
                                        <span className="min-w-0 truncate">
                                            {fund.name}
                                            {fund.year ? (
                                                <span className="ml-1.5 text-xs text-muted-foreground">
                                                    {fund.year}
                                                </span>
                                            ) : null}
                                        </span>
                                        {formatFundAmount(fund.amount) ? (
                                            <span className="shrink-0 font-mono text-xs text-muted-foreground tabular-nums">
                                                {formatFundAmount(fund.amount)}
                                            </span>
                                        ) : null}
                                    </li>
                                ))}
                            </ul>
                        )}
                    </section>

                    <section className="flex flex-col gap-4 rounded-xl border border-border bg-card p-4">
                        <div className="space-y-2">
                            <h2 className="flex items-center gap-1.5 text-[15px] font-semibold tracking-tight">
                                <Package className="size-3.5" />
                                Covered items
                            </h2>
                            {program_covered_items === undefined ? (
                                <OverviewDetailSkeleton />
                            ) : program_covered_items.length === 0 ? (
                                <p className="text-sm text-muted-foreground/60 italic">
                                    No items linked
                                </p>
                            ) : (
                                <div className="flex flex-wrap gap-1.5">
                                    {program_covered_items.map((item) => (
                                        <Badge
                                            key={item.id}
                                            variant="secondary"
                                        >
                                            {item.name}
                                            {item.unit ? (
                                                <span className="text-muted-foreground">
                                                    · {item.unit}
                                                </span>
                                            ) : null}
                                        </Badge>
                                    ))}
                                </div>
                            )}
                        </div>
                        <div className="space-y-2">
                            <h2 className="text-[15px] font-semibold tracking-tight">
                                Stock allocated
                            </h2>
                            {program_stock === undefined ? (
                                <OverviewDetailSkeleton />
                            ) : program_stock.length === 0 ? (
                                <p className="text-sm text-muted-foreground/60 italic">
                                    No stock allocated to this program
                                </p>
                            ) : (
                                <ul className="space-y-3">
                                    {program_stock.map((row) => {
                                        const cap =
                                            row.on_hand > 0
                                                ? row.on_hand
                                                : row.remaining;
                                        const stockPercent =
                                            cap > 0
                                                ? Math.min(
                                                      100,
                                                      (row.remaining / cap) *
                                                          100,
                                                  )
                                                : 0;

                                        return (
                                            <li
                                                key={row.item_id}
                                                className="space-y-1"
                                            >
                                                <div className="flex items-center justify-between gap-2 text-sm">
                                                    <span>
                                                        {row.item_name}
                                                        {row.unit ? (
                                                            <span className="text-muted-foreground">
                                                                {' '}
                                                                · {row.unit}
                                                            </span>
                                                        ) : null}
                                                        {row.is_low ? (
                                                            <Badge
                                                                variant="destructive"
                                                                className="ml-2"
                                                            >
                                                                Low
                                                            </Badge>
                                                        ) : null}
                                                    </span>
                                                    <span className="font-mono text-xs text-muted-foreground tabular-nums">
                                                        {row.remaining}{' '}
                                                        remaining
                                                    </span>
                                                </div>
                                                <Progress
                                                    value={stockPercent}
                                                />
                                            </li>
                                        );
                                    })}
                                </ul>
                            )}
                        </div>
                    </section>
                </div>

                <section
                    data-tour="program-assistance"
                    className="rounded-xl border border-border bg-card"
                >
                    <div className="flex flex-col gap-4 p-4">
                        <div className="space-y-1">
                            <h2 className="text-[15px] font-semibold tracking-tight">
                                Assistance
                            </h2>
                            <p className="text-xs text-muted-foreground">
                                Assistance records for this program.
                            </p>
                        </div>

                        {resolvedAssistanceTable ? (
                            <Suspense fallback={tableSkeleton}>
                                <ProgramAssistanceTableSection
                                    assistances={
                                        resolvedAssistanceTable.assistances
                                    }
                                    tableFilters={tableFilters}
                                    tableState={tableState}
                                    statusOptions={
                                        resolvedAssistanceTable.status_options
                                    }
                                    modeOptions={
                                        resolvedAssistanceTable.mode_options
                                    }
                                    isLoading={isTableReloading}
                                    departmentSlug={department?.slug ?? ''}
                                    programId={program.id}
                                    programName={program.name}
                                    isOrganization={
                                        program.is_organization ?? false
                                    }
                                    distributionMode={program.distribution_mode}
                                    canCreateAssistance={canCreateAssistance}
                                    modeOfRequestOptions={
                                        resolvedAssistanceTable.mode_of_request_options
                                    }
                                    programItems={
                                        resolvedAssistanceTable.program_items
                                    }
                                    programFields={
                                        resolvedAssistanceTable.program_fields
                                    }
                                    requestSubStatusOptions={
                                        resolvedAssistanceTable.request_sub_status_options
                                    }
                                    transferProgramOptions={
                                        resolvedAssistanceTable.transfer_program_options
                                    }
                                    canTransferAssistance={
                                        canTransferAssistance
                                    }
                                    documentRequirements={document_requirements}
                                    onVisitTable={visitTable}
                                />
                            </Suspense>
                        ) : (
                            <WhenVisible
                                data={[...ASSISTANCE_TABLE_DEFER_GROUP_PROPS]}
                                buffer={200}
                                fallback={() => tableSkeleton}
                            >
                                {tableSkeleton}
                            </WhenVisible>
                        )}
                    </div>
                </section>
            </div>

            <ProgramAnalyticsDialog
                program={analyticsOpen ? program : null}
                open={analyticsOpen}
                onOpenChange={setAnalyticsOpen}
                url={
                    department?.slug
                        ? programAnalytics.url({
                              department: department.slug,
                              program: program.id,
                          })
                        : null
                }
                exportUrl={
                    canExportAssistance && department?.slug
                        ? exportAssistances.url({
                              department: department.slug,
                              program: program.id,
                          })
                        : null
                }
            />

            {canEdit && department ? (
                <Suspense fallback={null}>
                    <ProgramEditDrawer
                        open={editOpen}
                        onOpenChange={setEditOpen}
                        program={program}
                        department={department}
                        programEdit={program_edit}
                        funds={funds}
                        items={items}
                        documentTypes={document_types}
                        workflowOptions={workflow_options}
                        formKey={editFormKey}
                        onClose={closeEditDrawer}
                    />
                </Suspense>
            ) : null}
        </>
    );
}
