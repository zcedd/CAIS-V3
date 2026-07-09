import { DataTable } from '@/components/data-table';
import { DataTableSkeleton } from '@/components/data-table/data-table-skeleton';
import {
    createUserProgramAssistanceColumns,
    userProgramAssistanceInitialColumnVisibility,
    type UserProgramAssistanceRow,
} from '@/pages/user/programs/assistance-columns';
import {
    AssistanceDataTableToolbar,
    type AssistanceModeOption,
    type AssistanceProgramItemOption,
    type AssistanceRequestSubStatusOption,
    type AssistanceTransferProgramOption,
    type AssistanceTableFilters,
    type ModeFilterOption,
    type StatusFilterOption,
} from '@/pages/user/programs/assistance-toolbar';
import { AssistanceBulkActionsBar } from '@/pages/user/programs/assistance-bulk-actions-bar';
import { useMemo } from 'react';

export const ASSISTANCE_TABLE_PARTIAL_PROPS = ['assistances'] as const;

export const ASSISTANCE_TABLE_DEFER_GROUP_PROPS = [
    'assistances',
    'mode_options',
    'status_options',
    'mode_of_request_options',
    'program_items',
    'request_sub_status_options',
    'transfer_program_options',
] as const;

export const ASSISTANCE_TABLE_SKELETON_COLUMNS = 14;

export type PaginatedAssistances = {
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

export function buildTableQuery(
    current: AssistanceTableFilters & {
        sort: string;
        direction: 'asc' | 'desc';
        per_page: number;
    },
    overrides: Partial<
        AssistanceTableFilters & {
            sort: string;
            direction: 'asc' | 'desc';
            per_page: number;
            page: number;
        }
    > = {},
): Record<string, string | number | string[]> {
    const search = overrides.search ?? current.search;
    const status = overrides.status ?? current.status;
    const mode = overrides.mode ?? current.mode;

    const query: Record<string, string | number | string[]> = {
        sort: overrides.sort ?? current.sort,
        direction: overrides.direction ?? current.direction,
        per_page: overrides.per_page ?? current.per_page,
        page: overrides.page ?? 1,
    };

    if (search !== '') {
        query.search = search;
    }

    if (status.length > 0) {
        query.status = status;
    }

    if (mode.length > 0) {
        query.mode = mode;
    }

    return query;
}

export function isAssistancesTableReady(props: {
    assistances?: PaginatedAssistances;
    mode_options?: StatusFilterOption[];
    status_options?: StatusFilterOption[];
    mode_of_request_options?: AssistanceModeOption[];
    program_items?: AssistanceProgramItemOption[];
    request_sub_status_options?: AssistanceRequestSubStatusOption[];
    transfer_program_options?: AssistanceTransferProgramOption[];
}): props is {
    assistances: PaginatedAssistances;
    mode_options: StatusFilterOption[];
    status_options: StatusFilterOption[];
    mode_of_request_options: AssistanceModeOption[];
    program_items: AssistanceProgramItemOption[];
    request_sub_status_options: AssistanceRequestSubStatusOption[];
    transfer_program_options: AssistanceTransferProgramOption[];
} {
    return (
        props.assistances !== undefined &&
        props.mode_options !== undefined &&
        props.status_options !== undefined &&
        props.mode_of_request_options !== undefined &&
        props.program_items !== undefined &&
        props.request_sub_status_options !== undefined &&
        props.transfer_program_options !== undefined
    );
}

export function isAssistancesPartialVisit(only?: string[]): boolean {
    if (!only?.length) {
        return false;
    }

    return only.some((prop) =>
        ASSISTANCE_TABLE_PARTIAL_PROPS.includes(
            prop as (typeof ASSISTANCE_TABLE_PARTIAL_PROPS)[number],
        ),
    );
}

type ProgramAssistanceTableProps = {
    assistances: PaginatedAssistances;
    assistanceColumns: ReturnType<typeof createUserProgramAssistanceColumns>;
    tableFilters: AssistanceTableFilters;
    tableState: {
        sort: string;
        direction: 'asc' | 'desc';
        per_page: number;
        search: string;
        status: string[];
        mode: string[];
    };
    statusOptions: StatusFilterOption[];
    modeOptions: ModeFilterOption[];
    isLoading: boolean;
    departmentSlug: string;
    programId: number;
    programName: string;
    isOrganization: boolean;
    canCreateAssistance: boolean;
    modeOfRequestOptions: AssistanceModeOption[];
    programItems: AssistanceProgramItemOption[];
    requestSubStatusOptions: AssistanceRequestSubStatusOption[];
    transferProgramOptions: AssistanceTransferProgramOption[];
    canTransferAssistance: boolean;
    onVisitTable: (
        overrides: Partial<
            AssistanceTableFilters & {
                sort: string;
                direction: 'asc' | 'desc';
                per_page: number;
                page: number;
            }
        >,
    ) => void;
};

type ProgramAssistanceTableSectionProps = {
    assistances: PaginatedAssistances;
    tableFilters: AssistanceTableFilters;
    tableState: ProgramAssistanceTableProps['tableState'];
    statusOptions: StatusFilterOption[];
    modeOptions: ModeFilterOption[];
    isLoading: boolean;
    departmentSlug: string;
    programId: number;
    programName: string;
    isOrganization: boolean;
    canCreateAssistance: boolean;
    modeOfRequestOptions: AssistanceModeOption[];
    programItems: AssistanceProgramItemOption[];
    requestSubStatusOptions: AssistanceRequestSubStatusOption[];
    transferProgramOptions: AssistanceTransferProgramOption[];
    canTransferAssistance: boolean;
    onVisitTable: ProgramAssistanceTableProps['onVisitTable'];
};

function ProgramAssistanceTable({
    assistances,
    assistanceColumns,
    tableFilters,
    tableState,
    statusOptions,
    modeOptions,
    isLoading,
    departmentSlug,
    programId,
    programName,
    isOrganization,
    canCreateAssistance,
    modeOfRequestOptions,
    programItems,
    requestSubStatusOptions,
    transferProgramOptions,
    canTransferAssistance,
    onVisitTable,
}: ProgramAssistanceTableProps) {
    return (
        <DataTable
            columns={assistanceColumns}
            data={assistances.data}
            emptyMessage="No assistance records for this program."
            manualPagination
            manualSorting
            manualFiltering
            serverPagination={assistances}
            serverSorting={{
                sort: tableState.sort,
                direction: tableState.direction,
            }}
            partialReloadOnly={[...ASSISTANCE_TABLE_PARTIAL_PROPS]}
            isLoading={isLoading}
            loadingFallback={
                <DataTableSkeleton
                    columnCount={ASSISTANCE_TABLE_SKELETON_COLUMNS}
                    rowCount={tableState.per_page}
                />
            }
            onServerSortingChange={(columnId, nextDirection) => {
                onVisitTable({
                    sort: columnId,
                    direction: nextDirection,
                    page: 1,
                });
            }}
            onPerPageChange={(nextPerPage) => {
                onVisitTable({ per_page: nextPerPage, page: 1 });
            }}
            selectionActions={({ table, rowSelection, selectedCount }) => (
                <AssistanceBulkActionsBar
                    table={table}
                    rowSelection={rowSelection}
                    selectedCount={selectedCount}
                    departmentSlug={departmentSlug}
                    programId={programId}
                    programName={programName}
                    requestSubStatusOptions={requestSubStatusOptions}
                    transferProgramOptions={transferProgramOptions}
                    canTransferAssistance={canTransferAssistance}
                    onBulkStatusUpdated={() => onVisitTable({})}
                />
            )}
            toolbar={(table, columnVisibility) => (
                <AssistanceDataTableToolbar
                    table={table}
                    columnVisibility={columnVisibility}
                    filters={tableFilters}
                    statusOptions={statusOptions}
                    modeOptions={modeOptions}
                    onFiltersChange={onVisitTable}
                    sort={tableState.sort}
                    direction={tableState.direction}
                    departmentSlug={departmentSlug}
                    programId={programId}
                    programName={programName}
                    isOrganization={isOrganization}
                    canCreate={canCreateAssistance}
                    modeOfRequestOptions={modeOfRequestOptions}
                    programItems={programItems}
                    onAssistanceCreated={() => onVisitTable({ page: 1 })}
                />
            )}
            initialColumnVisibility={
                userProgramAssistanceInitialColumnVisibility
            }
            enableRowSelection
        />
    );
}

export function ProgramAssistanceTableSection({
    assistances,
    tableFilters,
    tableState,
    statusOptions,
    modeOptions,
    isLoading,
    departmentSlug,
    programId,
    programName,
    isOrganization,
    canCreateAssistance,
    modeOfRequestOptions,
    programItems,
    requestSubStatusOptions,
    transferProgramOptions,
    canTransferAssistance,
    onVisitTable,
}: ProgramAssistanceTableSectionProps) {
    const assistanceColumns = useMemo(
        () =>
            createUserProgramAssistanceColumns({
                departmentSlug,
                programId,
                programName,
                isOrganization,
                modeOfRequestOptions,
                programItems,
                requestSubStatusOptions,
                transferProgramOptions,
                canTransferAssistance,
                onAssistanceUpdated: () => onVisitTable({ page: 1 }),
            }),
        [
            departmentSlug,
            programId,
            programName,
            isOrganization,
            modeOfRequestOptions,
            programItems,
            requestSubStatusOptions,
            transferProgramOptions,
            canTransferAssistance,
            onVisitTable,
        ],
    );

    return (
        <ProgramAssistanceTable
            assistances={assistances}
            assistanceColumns={assistanceColumns}
            tableFilters={tableFilters}
            tableState={tableState}
            statusOptions={statusOptions}
            modeOptions={modeOptions}
            isLoading={isLoading}
            departmentSlug={departmentSlug}
            programId={programId}
            programName={programName}
            isOrganization={isOrganization}
            canCreateAssistance={canCreateAssistance}
            modeOfRequestOptions={modeOfRequestOptions}
            programItems={programItems}
            requestSubStatusOptions={requestSubStatusOptions}
            transferProgramOptions={transferProgramOptions}
            canTransferAssistance={canTransferAssistance}
            onVisitTable={onVisitTable}
        />
    );
}
