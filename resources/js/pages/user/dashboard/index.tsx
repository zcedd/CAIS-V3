import { Chart as ChartSkeleton } from '@/components/skeleton/chart';
import { DataTableSkeleton } from '@/components/data-table/data-table-skeleton';
import { DashboardFiltersBar } from '@/pages/user/dashboard/dashboard-filters';
import { DeliveredItemsChart } from '@/pages/user/dashboard/delivered-items-chart';
import { KpiCards } from '@/pages/user/dashboard/kpi-cards';
import { ProgramsTable } from '@/pages/user/dashboard/programs-table';
import { RequestStatusChart } from '@/pages/user/dashboard/request-status-chart';
import { index as departmentDashboardIndex } from '@/routes/user/dashboard';
import type {
    DashboardFilterOptions,
    DashboardFilters,
    DashboardProgramRow,
    DashboardSummary,
    DeliveredItemsChartPoint,
    DepartmentSummary,
    RequestStatusChartPoint,
} from '@/types/dashboard';
import type { BreadcrumbItem } from '@/types';
import { Head, setLayoutProps, WhenVisible } from '@inertiajs/react';
import { useEffect } from 'react';

type DashboardPageProps = {
    department: DepartmentSummary;
    summary: DashboardSummary;
    requestStatusChart?: RequestStatusChartPoint[];
    deliveredItemsChart?: DeliveredItemsChartPoint[];
    programsTable?: DashboardProgramRow[];
    filterOptions: DashboardFilterOptions;
    filters: DashboardFilters;
};

export default function UserDashboardIndex({
    department,
    summary,
    requestStatusChart,
    deliveredItemsChart,
    programsTable,
    filterOptions,
    filters,
}: DashboardPageProps) {
    useEffect(() => {
        setLayoutProps({
            breadcrumbs: [
                {
                    title: 'Dashboard',
                    href: departmentDashboardIndex(department.slug),
                },
                {
                    title: department.name,
                    href: departmentDashboardIndex(department.slug),
                },
            ] satisfies BreadcrumbItem[],
        });
    }, [department.name, department.slug]);

    return (
        <>
            <Head title={`Dashboard — ${department.name}`} />
            <div className="flex h-full flex-1 flex-col gap-4 overflow-x-auto rounded-xl p-4">
                <div className="flex flex-col gap-2 sm:flex-row sm:items-end sm:justify-between">
                    <div>
                        <h1 className="text-2xl font-semibold tracking-tight">
                            Dashboard
                        </h1>
                        <p className="text-sm text-muted-foreground">
                            Overview of requests, deliveries, and programs for{' '}
                            {department.name}
                        </p>
                    </div>
                </div>

                <DashboardFiltersBar
                    department={department}
                    filters={filters}
                    filterOptions={filterOptions}
                />

                <KpiCards summary={summary} />

                <div className="grid gap-4 lg:grid-cols-2">
                    <WhenVisible
                        data="requestStatusChart"
                        fallback={<ChartSkeleton />}
                    >
                        <RequestStatusChart
                            data={requestStatusChart ?? []}
                        />
                    </WhenVisible>
                    <WhenVisible
                        data="deliveredItemsChart"
                        fallback={<ChartSkeleton />}
                    >
                        <DeliveredItemsChart
                            data={deliveredItemsChart ?? []}
                        />
                    </WhenVisible>
                </div>

                <WhenVisible
                    data="programsTable"
                    fallback={
                        <DataTableSkeleton columnCount={6} rowCount={5} />
                    }
                >
                    <ProgramsTable data={programsTable ?? []} />
                </WhenVisible>
            </div>
        </>
    );
}
