import { Chart as ChartSkeleton } from '@/components/skeleton/chart';
import { DataTableSkeleton } from '@/components/data-table/data-table-skeleton';
import { DashboardFiltersBar } from '@/pages/user/dashboard/dashboard-filters';
import { DashboardFiltersSkeleton } from '@/pages/user/dashboard/dashboard-filters-skeleton';
import { KpiCards, KpiCardsSkeleton } from '@/pages/user/dashboard/kpi-cards';
import { index as departmentDashboardIndex } from '@/routes/user/dashboard';
import {
    DASHBOARD_CHART_DEFER_PROPS,
    type DashboardFilterOptions,
    type DashboardFilters,
    type DashboardProgramRow,
    type DashboardSummary,
    type DeliveredItemsChartPoint,
    type DepartmentSummary,
    type RequestStatusChartPoint,
} from '@/types/dashboard';
import type { BreadcrumbItem } from '@/types';
import { Head, setLayoutProps, WhenVisible } from '@inertiajs/react';
import { lazy, Suspense, useEffect } from 'react';

const RequestStatusChart = lazy(() =>
    import('@/pages/user/dashboard/request-status-chart').then((module) => ({
        default: module.RequestStatusChart,
    })),
);

const DeliveredItemsChart = lazy(() =>
    import('@/pages/user/dashboard/delivered-items-chart').then((module) => ({
        default: module.DeliveredItemsChart,
    })),
);

const ProgramsTable = lazy(() =>
    import('@/pages/user/dashboard/programs-table').then((module) => ({
        default: module.ProgramsTable,
    })),
);

type DashboardPageProps = {
    department: DepartmentSummary;
    summary?: DashboardSummary;
    requestStatusChart?: RequestStatusChartPoint[];
    deliveredItemsChart?: DeliveredItemsChartPoint[];
    programsTable?: DashboardProgramRow[];
    filterOptions?: DashboardFilterOptions;
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

                <WhenVisible
                    data="filterOptions"
                    buffer={200}
                    fallback={<DashboardFiltersSkeleton />}
                >
                    {filterOptions ? (
                        <DashboardFiltersBar
                            department={department}
                            filters={filters}
                            filterOptions={filterOptions}
                        />
                    ) : (
                        <DashboardFiltersSkeleton />
                    )}
                </WhenVisible>

                <WhenVisible
                    data="summary"
                    buffer={200}
                    fallback={<KpiCardsSkeleton />}
                >
                    {summary ? (
                        <KpiCards summary={summary} />
                    ) : (
                        <KpiCardsSkeleton />
                    )}
                </WhenVisible>

                <div className="grid gap-4 lg:grid-cols-2">
                    <WhenVisible
                        data={[...DASHBOARD_CHART_DEFER_PROPS]}
                        buffer={200}
                        fallback={<ChartSkeleton />}
                    >
                        <Suspense fallback={<ChartSkeleton />}>
                            <RequestStatusChart
                                data={requestStatusChart ?? []}
                            />
                        </Suspense>
                    </WhenVisible>
                    <WhenVisible
                        data={[...DASHBOARD_CHART_DEFER_PROPS]}
                        buffer={200}
                        fallback={<ChartSkeleton />}
                    >
                        <Suspense fallback={<ChartSkeleton />}>
                            <DeliveredItemsChart
                                data={deliveredItemsChart ?? []}
                            />
                        </Suspense>
                    </WhenVisible>
                </div>

                <WhenVisible
                    data="programsTable"
                    buffer={200}
                    fallback={
                        <DataTableSkeleton columnCount={6} rowCount={5} />
                    }
                >
                    <Suspense
                        fallback={
                            <DataTableSkeleton columnCount={6} rowCount={5} />
                        }
                    >
                        <ProgramsTable data={programsTable ?? []} />
                    </Suspense>
                </WhenVisible>
            </div>
        </>
    );
}
