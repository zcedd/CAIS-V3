import { DataTableSkeleton } from '@/components/data-table/data-table-skeleton';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
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
    index as departmentProgramsIndex,
    show as departmentProgramShow,
} from '@/routes/user/programs';
import type { BreadcrumbItem } from '@/types';
import type { ProgramSummary } from '@/types/program';
import { Head, router, setLayoutProps, WhenVisible } from '@inertiajs/react';
import { Pencil } from 'lucide-react';
import { lazy, Suspense, useCallback, useEffect, useRef, useState } from 'react';

const ProgramAssistanceTableSection = lazy(() =>
    import('@/pages/user/programs/program-assistance-table').then((module) => ({
        default: module.ProgramAssistanceTableSection,
    })),
);

const ProgramEditDrawer = lazy(() =>
    import('@/pages/user/programs/program-edit-drawer').then((module) => ({
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
    department_id: number;
};

type ProgramEditRelations = {
    fund_ids: number[];
    item_ids: number[];
};

export default function UserProgramShow({
    program,
    summary,
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
    request_sub_status_options,
    transfer_program_options,
}: {
    program: ProgramDetail;
    summary?: ProgramSummary;
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
    request_sub_status_options?: AssistanceRequestSubStatusOption[];
    transfer_program_options?: AssistanceTransferProgramOption[];
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

    const closeEditDrawer = useCallback(() => {
        setEditOpen(false);
    }, []);

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

                <Card data-tour="program-overview">
                    <CardHeader className="gap-1">
                        <CardTitle className="text-lg">Overview</CardTitle>
                        <CardDescription>
                            <div className="flex gap-2">
                                <Badge variant="default">
                                    {program.is_organization
                                        ? 'Organization'
                                        : 'Individual'}
                                </Badge>
                                <Badge
                                    variant={
                                        program.is_closed
                                            ? 'destructive'
                                            : 'default'
                                    }
                                >
                                    {program.is_closed ? 'Closed' : 'Open'}
                                </Badge>
                            </div>
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
                    </CardContent>
                </Card>

                <Card data-tour="program-assistance">
                    <CardHeader className="gap-1">
                        <CardTitle className="text-lg">Assistance</CardTitle>
                        <CardDescription>
                            Filter, sort, and manage assistance records for this
                            program.
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
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
                                            modeOptions={tableProps.mode_options}
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
                    </CardContent>
                </Card>
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
                        formKey={editFormKey}
                        onClose={closeEditDrawer}
                    />
                </Suspense>
            ) : null}
        </>
    );
}
