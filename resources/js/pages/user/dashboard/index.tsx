import { Head, setLayoutProps, WhenVisible } from '@inertiajs/react';
import { lazy, Suspense, useEffect } from 'react';
import { DataTableSkeleton } from '@/components/data-table/data-table-skeleton';
import { Chart as ChartSkeleton } from '@/components/skeleton/chart';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import { StatCard } from '@/components/user/stat-card';
import { DashboardFiltersButton } from '@/pages/user/dashboard/dashboard-filters';
import { DashboardFiltersSkeleton } from '@/pages/user/dashboard/dashboard-filters-skeleton';
import { InsightsPanelSkeleton } from '@/pages/user/dashboard/insights-panel';
import {
    IntelligencePanel,
    IntelligencePanelSkeleton,
} from '@/pages/user/dashboard/intelligence-panel';
import { KpiCardsSkeleton } from '@/pages/user/dashboard/kpi-cards';
import { MunicipalityMapSkeleton } from '@/pages/user/dashboard/municipality-map-skeleton';
import { TopDeliveredItemsSkeleton } from '@/pages/user/dashboard/top-delivered-items';
import { index as departmentDashboardIndex } from '@/routes/user/dashboard';
import type { BreadcrumbItem } from '@/types';
import {
    DASHBOARD_CHART_DEFER_PROPS,
    DASHBOARD_INSIGHT_DEFER_PROPS,
} from '@/types/dashboard';
import type {
    BeneficiaryTypeChartPoint,
    DashboardDemographics,
    DashboardFilterOptions,
    DashboardFilters,
    DashboardInsights,
    DashboardProgramRow,
    DashboardRecentBeneficiaryRow,
    DashboardSummary,
    DeliveredItemsChartPoint,
    DemographicCount,
    DepartmentSummary,
    RequestStatusChartPoint,
    RequestsTrendPoint,
    TopBarangayPoint,
    UnspscReleasedChartPoint,
    CityChartPoint,
    MunicipalityMapPoint,
} from '@/types/dashboard';

const ProgramsTable = lazy(() =>
    import('@/pages/user/dashboard/programs-table').then((module) => ({
        default: module.ProgramsTable,
    })),
);

const RecentBeneficiariesTable = lazy(() =>
    import('@/pages/user/dashboard/recent-beneficiaries-table').then(
        (module) => ({
            default: module.RecentBeneficiariesTable,
        }),
    ),
);

const InsightsPanel = lazy(() =>
    import('@/pages/user/dashboard/insights-panel').then((module) => ({
        default: module.InsightsPanel,
    })),
);

const RequestStatusChart = lazy(() =>
    import('@/pages/user/dashboard/request-status-chart').then((module) => ({
        default: module.RequestStatusChart,
    })),
);

const UnspscReleasedChart = lazy(() =>
    import('@/pages/user/dashboard/unspsc-released-chart').then((module) => ({
        default: module.UnspscReleasedChart,
    })),
);

const TopDeliveredItems = lazy(() =>
    import('@/pages/user/dashboard/top-delivered-items').then((module) => ({
        default: module.TopDeliveredItems,
    })),
);

const RequestsTrendChart = lazy(() =>
    import('@/pages/user/dashboard/requests-trend-chart').then((module) => ({
        default: module.RequestsTrendChart,
    })),
);

const MunicipalityMap = lazy(() =>
    import('@/pages/user/dashboard/municipality-map').then((module) => ({
        default: module.MunicipalityMap,
    })),
);

type DashboardPageProps = {
    department: DepartmentSummary;
    summary?: DashboardSummary;
    requestStatusChart?: RequestStatusChartPoint[];
    deliveredItemsChart?: DeliveredItemsChartPoint[];
    unspscReleasedChart?: UnspscReleasedChartPoint[];
    beneficiaryTypeChart?: BeneficiaryTypeChartPoint[];
    demographics?: DashboardDemographics;
    requestsTrend?: RequestsTrendPoint[];
    insights?: DashboardInsights;
    topBarangays?: TopBarangayPoint[];
    modeOfRequestChart?: DemographicCount[];
    programsTable?: DashboardProgramRow[];
    recentBeneficiaries?: DashboardRecentBeneficiaryRow[];
    cityChart?: CityChartPoint[];
    municipalityMap?: MunicipalityMapPoint[];
    filterOptions?: DashboardFilterOptions;
    filters?: DashboardFilters;
};

export default function UserDashboardIndex({
    department,
    summary,
    requestStatusChart,
    deliveredItemsChart,
    unspscReleasedChart,
    demographics,
    requestsTrend,
    insights,
    topBarangays,
    modeOfRequestChart,
    programsTable,
    recentBeneficiaries,
    cityChart,
    municipalityMap,
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
            <Head title={department.name} />
            <div className="flex min-w-0 flex-1 flex-col gap-4 overflow-x-clip rounded-xl p-4">
                <div className="flex flex-col gap-2 sm:flex-row sm:items-end sm:justify-between">
                    <div className="flex flex-col gap-1">
                        <h1 className="text-2xl font-semibold tracking-tight">
                            {department.name}
                        </h1>
                        <p className="text-sm text-muted-foreground">
                            Requests, deliveries, and programs
                        </p>
                    </div>
                    <WhenVisible
                        data={['filterOptions', 'filters']}
                        buffer={200}
                        fallback={<DashboardFiltersSkeleton />}
                    >
                        {filterOptions && filters ? (
                            <DashboardFiltersButton
                                department={department}
                                filters={filters}
                                filterOptions={filterOptions}
                            />
                        ) : (
                            <DashboardFiltersSkeleton />
                        )}
                    </WhenVisible>
                </div>

                <Tabs defaultValue="overview" className="gap-4">
                    <div className="no-scrollbar w-full overflow-x-auto overflow-y-hidden">
                        <TabsList data-tour="dashboard-tabs">
                            <TabsTrigger
                                value="overview"
                                data-tour="dashboard-tab-overview"
                            >
                                Overview
                            </TabsTrigger>
                            <TabsTrigger
                                value="insights"
                                data-tour="dashboard-tab-insights"
                            >
                                Insights
                            </TabsTrigger>
                            <TabsTrigger
                                value="programs"
                                data-tour="dashboard-tab-programs"
                            >
                                Programs
                            </TabsTrigger>
                        </TabsList>
                    </div>

                    <TabsContent value="overview" className="space-y-4">
                        <div data-tour="dashboard-assistance-overview">
                            <WhenVisible
                                data={['summary', 'cityChart', 'demographics']}
                                buffer={200}
                                fallback={<IntelligencePanelSkeleton />}
                            >
                                {summary ? (
                                    <IntelligencePanel
                                        summary={summary}
                                        cities={cityChart ?? []}
                                        demographics={demographics ?? null}
                                        map={
                                            <div
                                                data-tour="dashboard-municipality-map"
                                                className="flex h-full min-w-0 flex-col"
                                            >
                                                <WhenVisible
                                                    data="municipalityMap"
                                                    buffer={200}
                                                    fallback={
                                                        <MunicipalityMapSkeleton />
                                                    }
                                                >
                                                    <Suspense
                                                        fallback={
                                                            <MunicipalityMapSkeleton />
                                                        }
                                                    >
                                                        <MunicipalityMap
                                                            municipalities={
                                                                municipalityMap ??
                                                                []
                                                            }
                                                        />
                                                    </Suspense>
                                                </WhenVisible>
                                            </div>
                                        }
                                    />
                                ) : (
                                    <IntelligencePanelSkeleton />
                                )}
                            </WhenVisible>
                        </div>

                        <WhenVisible
                            data="recentBeneficiaries"
                            buffer={200}
                            fallback={
                                <DataTableSkeleton
                                    columnCount={6}
                                    rowCount={5}
                                />
                            }
                        >
                            <Suspense
                                fallback={
                                    <DataTableSkeleton
                                        columnCount={6}
                                        rowCount={5}
                                    />
                                }
                            >
                                <RecentBeneficiariesTable
                                    department={department}
                                    data={recentBeneficiaries ?? []}
                                />
                            </Suspense>
                        </WhenVisible>
                    </TabsContent>

                    <TabsContent value="insights" className="space-y-4">
                        <div data-tour="dashboard-insights">
                            <WhenVisible
                                data={[...DASHBOARD_INSIGHT_DEFER_PROPS]}
                                buffer={200}
                                fallback={<InsightsPanelSkeleton />}
                            >
                                <Suspense fallback={<InsightsPanelSkeleton />}>
                                    {insights && summary ? (
                                        <InsightsPanel
                                            insights={insights}
                                            summary={summary}
                                            programs={programsTable ?? []}
                                            topBarangays={topBarangays ?? []}
                                            modeOfRequestChart={
                                                modeOfRequestChart ?? []
                                            }
                                        />
                                    ) : (
                                        <InsightsPanelSkeleton />
                                    )}
                                </Suspense>
                            </WhenVisible>
                        </div>

                        <WhenVisible
                            data={[...DASHBOARD_CHART_DEFER_PROPS]}
                            buffer={200}
                            fallback={<ChartSkeleton />}
                        >
                            <Suspense fallback={<ChartSkeleton />}>
                                <RequestsTrendChart
                                    data={requestsTrend ?? []}
                                />
                            </Suspense>
                        </WhenVisible>

                        <div className="grid gap-4 lg:grid-cols-2">
                            <div className="h-full min-w-0 [&>*]:h-full">
                                <WhenVisible
                                    data={[...DASHBOARD_CHART_DEFER_PROPS]}
                                    buffer={200}
                                    fallback={<TopDeliveredItemsSkeleton />}
                                >
                                    <Suspense
                                        fallback={<TopDeliveredItemsSkeleton />}
                                    >
                                        <TopDeliveredItems
                                            data={deliveredItemsChart ?? []}
                                        />
                                    </Suspense>
                                </WhenVisible>
                            </div>
                            <div className="h-full min-w-0 [&>*]:h-full">
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
                            </div>
                        </div>

                        <WhenVisible
                            data={[...DASHBOARD_CHART_DEFER_PROPS]}
                            buffer={200}
                            fallback={<ChartSkeleton />}
                        >
                            <Card size="sm">
                                <CardHeader>
                                    <CardTitle className="text-sm">
                                        Released by UNSPSC segment
                                    </CardTitle>
                                    <CardDescription className="text-xs">
                                        Quantity released, classified for
                                        PhilGEPS, COA, and donors. Spend
                                        reporting waits on item cost.
                                    </CardDescription>
                                </CardHeader>
                                <CardContent>
                                    <Suspense fallback={<ChartSkeleton />}>
                                        <UnspscReleasedChart
                                            data={unspscReleasedChart ?? []}
                                        />
                                    </Suspense>
                                </CardContent>
                            </Card>
                        </WhenVisible>
                    </TabsContent>

                    <TabsContent value="programs" className="space-y-4">
                        <div data-tour="dashboard-kpis">
                            <WhenVisible
                                data="summary"
                                buffer={200}
                                fallback={<KpiCardsSkeleton variant="strip" />}
                            >
                                {summary ? (
                                    <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                                        <StatCard
                                            label="Active programs"
                                            value={summary.active_programs.toLocaleString()}
                                        />
                                        <StatCard
                                            label="Closed programs"
                                            value={summary.closed_programs.toLocaleString()}
                                        />
                                        <StatCard
                                            label="Delivered items"
                                            value={summary.total_delivered_items.toLocaleString()}
                                        />
                                        <StatCard
                                            label="Denied requests"
                                            value={summary.denied_requests.toLocaleString()}
                                        />
                                    </div>
                                ) : (
                                    <KpiCardsSkeleton />
                                )}
                            </WhenVisible>
                        </div>

                        <div data-tour="dashboard-programs-summary">
                            <WhenVisible
                                data="programsTable"
                                buffer={200}
                                fallback={
                                    <DataTableSkeleton
                                        columnCount={8}
                                        rowCount={5}
                                    />
                                }
                            >
                                <Suspense
                                    fallback={
                                        <DataTableSkeleton
                                            columnCount={8}
                                            rowCount={5}
                                        />
                                    }
                                >
                                    <ProgramsTable
                                        department={department}
                                        data={programsTable ?? []}
                                    />
                                </Suspense>
                            </WhenVisible>
                        </div>
                    </TabsContent>
                </Tabs>
            </div>
        </>
    );
}
