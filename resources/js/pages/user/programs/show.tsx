import { DataTableSkeleton } from '@/components/data-table/data-table-skeleton';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Progress } from '@/components/ui/progress';
import { Skeleton } from '@/components/ui/skeleton';
import type {
    AssistanceModeOption,
    AssistanceProgramItemOption,
    AssistanceRequestSubStatusOption,
    AssistanceSelectOption,
    AssistanceTableFilters,
    AssistanceTransferProgramOption,
    ModeFilterOption,
    StatusFilterOption,
} from '@/pages/user/programs/assistance-toolbar';
import {
    ProgramKpiCards,
    ProgramKpiCardsSkeleton,
} from '@/pages/user/programs/kpi-cards';
import {
    ASSISTANCE_TABLE_DEFER_GROUP_PROPS,
    ASSISTANCE_TABLE_SKELETON_COLUMNS,
    buildTableQuery,
    isAssistancesPartialVisit,
    isAssistancesTableReady,
    type PaginatedAssistances,
} from '@/pages/user/programs/program-assistance-table';
import {
    ProgramStatusBreakdown,
    ProgramStatusBreakdownSkeleton,
} from '@/pages/user/programs/status-breakdown';
import {
    index as departmentProgramsIndex,
    show as departmentProgramShow,
} from '@/routes/user/programs';
import {
    formatProgramDate,
    formatProgramPeriod,
} from '@/lib/format-program-period';
import { cn } from '@/lib/utils';
import type { BreadcrumbItem } from '@/types';
import type { DocumentTypeOption, ProgramDocumentRequirementInput } from '@/types/document';
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
import { Head, router, setLayoutProps, WhenVisible } from '@inertiajs/react';
import {
    Building2,
    CalendarRange,
    Coins,
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
    useRef,
    useState,
} from 'react';

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
    kind?: string | null;
    batch_name?: string | null;
    department_id: number;
    parent?: { id: number; name: string } | null;
};

type ProgramEditRelations = {
    fund_ids: number[];
    item_ids: number[];
    fields: ProgramFieldDefinition[];
    document_requirements?: ProgramDocumentRequirementInput[];
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
    document_types = [],
}: {
    program: ProgramDetail;
    summary?: ProgramSummary;
    status_breakdown?: ProgramStatusBreakdownPoint[];
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
    transfer_program_options?: AssistanceTransferProgramOption[];
    document_types?: DocumentTypeOption[];
}) {
    const [editOpen, setEditOpen] = useState(false);
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

    const tableStateRef = useRef(tableState);

    useEffect(() => {
        tableStateRef.current = tableState;
    }, [tableState]);

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
    }, [department?.slug, program.id, program.name, program.batch_name, program.parent]);

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
    const canEdit = Boolean(department?.slug);
    const canCreateAssistance = Boolean(department?.slug && !program.is_closed);
    const canTransferAssistance = Boolean(
        department?.slug &&
        !program.is_closed &&
        (transfer_program_options?.length ?? 0) > 0,
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
                            <Badge
                                variant="outline"
                                className={cn(
                                    isClosed
                                        ? 'border-border text-muted-foreground'
                                        : 'border-emerald-600/30 bg-emerald-600/10 text-emerald-700 dark:text-emerald-400',
                                )}
                            >
                                <span
                                    aria-hidden
                                    className={cn(
                                        'size-1.5 rounded-full',
                                        isClosed
                                            ? 'bg-muted-foreground/50'
                                            : 'bg-emerald-600',
                                    )}
                                />
                                {isClosed ? 'Closed' : 'Open'}
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
                            {program.kind === 'batch' && program.batch_name ? (
                                <Badge variant="outline">
                                    {program.batch_name}
                                </Badge>
                            ) : null}
                            {department ? (
                                <Badge variant="outline">
                                    <Building2 aria-hidden />
                                    {department.name}
                                </Badge>
                            ) : null}
                        </div>
                    </div>
                    <div className="flex flex-wrap gap-2">
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
                        data="summary"
                        buffer={200}
                        fallback={<ProgramKpiCardsSkeleton />}
                    >
                        {summary ? (
                            <ProgramKpiCards summary={summary} />
                        ) : (
                            <ProgramKpiCardsSkeleton />
                        )}
                    </WhenVisible>
                </div>

                <div data-tour="program-requests-status-chart">
                    <WhenVisible
                        data="status_breakdown"
                        buffer={200}
                        fallback={<ProgramStatusBreakdownSkeleton />}
                    >
                        {status_breakdown ? (
                            <ProgramStatusBreakdown
                                breakdown={status_breakdown}
                            />
                        ) : (
                            <ProgramStatusBreakdownSkeleton />
                        )}
                    </WhenVisible>
                </div>

                <section
                    data-tour="program-overview"
                    className="rounded-xl border border-border bg-card"
                >
                    <div className="flex flex-col gap-4 p-4">
                        <div className="flex items-start justify-between gap-3">
                            <div className="min-w-0 space-y-1">
                                <h2 className="text-[15px] font-semibold tracking-tight">
                                    Overview
                                </h2>
                                <p className="text-xs text-muted-foreground">
                                    Program details, schedule, funding, and
                                    covered items
                                </p>
                            </div>
                        </div>

                        <div className="grid gap-6 lg:grid-cols-3">
                            <div className="min-w-0 lg:col-span-2">
                                <p className="mb-1.5 text-xs font-medium tracking-wide text-muted-foreground uppercase">
                                    Description
                                </p>
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
                            </div>

                            <div className="min-w-0 space-y-5 lg:border-l lg:border-border lg:pl-6">
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
                                            {formatProgramDate(
                                                program.start_at_input,
                                            )}{' '}
                                            — no end date set
                                        </p>
                                    ) : null}
                                </div>

                                <div className="space-y-2">
                                    <p className="flex items-center gap-1.5 text-xs font-medium tracking-wide text-muted-foreground uppercase">
                                        <Coins className="size-3.5" />
                                        Funding sources
                                    </p>
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
                                                    {formatFundAmount(
                                                        fund.amount,
                                                    ) ? (
                                                        <span className="shrink-0 text-xs font-medium text-muted-foreground tabular-nums">
                                                            {formatFundAmount(
                                                                fund.amount,
                                                            )}
                                                        </span>
                                                    ) : null}
                                                </li>
                                            ))}
                                        </ul>
                                    )}
                                </div>

                                <div className="space-y-2">
                                    <p className="flex items-center gap-1.5 text-xs font-medium tracking-wide text-muted-foreground uppercase">
                                        <Package className="size-3.5" />
                                        Covered items
                                    </p>
                                    {program_covered_items === undefined ? (
                                        <OverviewDetailSkeleton />
                                    ) : program_covered_items.length === 0 ? (
                                        <p className="text-sm text-muted-foreground/60 italic">
                                            No items linked
                                        </p>
                                    ) : (
                                        <div className="flex flex-wrap gap-1.5">
                                            {program_covered_items.map(
                                                (item) => (
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
                                                ),
                                            )}
                                        </div>
                                    )}
                                </div>

                                <div className="space-y-2">
                                    <p className="flex items-center gap-1.5 text-xs font-medium tracking-wide text-muted-foreground uppercase">
                                        <Package className="size-3.5" />
                                        Stock allocated
                                    </p>
                                    {program_stock === undefined ? (
                                        <OverviewDetailSkeleton />
                                    ) : program_stock.length === 0 ? (
                                        <p className="text-sm text-muted-foreground/60 italic">
                                            No stock allocated to this program
                                        </p>
                                    ) : (
                                        <ul className="space-y-1.5">
                                            {program_stock.map((row) => (
                                                <li
                                                    key={row.item_id}
                                                    className="flex items-center justify-between gap-2 text-sm"
                                                >
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
                                                    <span className="tabular-nums text-muted-foreground">
                                                        {row.remaining} remaining
                                                    </span>
                                                </li>
                                            ))}
                                        </ul>
                                    )}
                                </div>
                            </div>
                        </div>
                    </div>
                </section>

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
                                Filter, sort, and manage assistance records for
                                this program.
                            </p>
                        </div>

                        <WhenVisible
                            data={[...ASSISTANCE_TABLE_DEFER_GROUP_PROPS]}
                            buffer={200}
                            fallback={() => tableSkeleton}
                        >
                            {(() => {
                                const tableProps = {
                                    assistances,
                                    mode_options,
                                    status_options,
                                    mode_of_request_options,
                                    program_items,
                                    program_fields,
                                    request_sub_status_options,
                                    transfer_program_options,
                                };

                                if (!isAssistancesTableReady(tableProps)) {
                                    return tableSkeleton;
                                }

                                return (
                                    <Suspense fallback={tableSkeleton}>
                                        <ProgramAssistanceTableSection
                                            assistances={tableProps.assistances}
                                            tableFilters={tableFilters}
                                            tableState={tableState}
                                            statusOptions={
                                                tableProps.status_options
                                            }
                                            modeOptions={
                                                tableProps.mode_options
                                            }
                                            isLoading={isTableReloading}
                                            departmentSlug={
                                                department?.slug ?? ''
                                            }
                                            programId={program.id}
                                            programName={program.name}
                                            isOrganization={
                                                program.is_organization ?? false
                                            }
                                            canCreateAssistance={
                                                canCreateAssistance
                                            }
                                            modeOfRequestOptions={
                                                tableProps.mode_of_request_options
                                            }
                                            programItems={
                                                tableProps.program_items
                                            }
                                            programFields={
                                                tableProps.program_fields
                                            }
                                            requestSubStatusOptions={
                                                tableProps.request_sub_status_options
                                            }
                                            transferProgramOptions={
                                                tableProps.transfer_program_options
                                            }
                                            canTransferAssistance={
                                                canTransferAssistance
                                            }
                                            onVisitTable={visitTable}
                                        />
                                    </Suspense>
                                );
                            })()}
                        </WhenVisible>
                    </div>
                </section>
            </div>

            {canEdit && department && editOpen ? (
                <Suspense fallback={null}>
                    <ProgramEditDrawer
                        key={editFormKey}
                        open={editOpen}
                        onOpenChange={setEditOpen}
                        program={program}
                        department={department}
                        programEdit={program_edit}
                        funds={funds}
                        items={items}
                        documentTypes={document_types}
                        formKey={editFormKey}
                        onClose={closeEditDrawer}
                    />
                </Suspense>
            ) : null}
        </>
    );
}
