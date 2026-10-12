import { Head, setLayoutProps, WhenVisible } from '@inertiajs/react';
import {
    Ban,
    ClipboardList,
    FolderKanban,
    FolderLock,
    LayoutDashboard,
    Package,
    Repeat,
    UserRound,
    Users,
} from 'lucide-react';
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
import { Separator } from '@/components/ui/separator';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import { cn } from '@/lib/utils';
import { DashboardFiltersBar } from '@/pages/executive/dashboard/dashboard-filters';
import { DashboardFiltersSkeleton } from '@/pages/user/dashboard/dashboard-filters-skeleton';
import {
    DashboardHighlights,
    DashboardHighlightsSkeleton,
    DeliverySnapshot,
    DeliverySnapshotSkeleton,
} from '@/pages/user/dashboard/dashboard-highlights';
import {
    DashboardIconWell,
    DashboardStatCard,
    dashboardHeadingClass,
    dashboardMutedClass,
    dashboardPageClass,
    dashboardSubheadingClass,
    dashboardSurfaceClass,
    dashboardTabListClass,
    dashboardTabTriggerClass,
    dashboardTitleClass,
} from '@/pages/user/dashboard/dashboard-stat-card';
import { DemographicsPanelSkeleton } from '@/pages/user/dashboard/demographics-panel';
import { InsightsPanelSkeleton } from '@/pages/user/dashboard/insights-panel';
import { KpiCards, KpiCardsSkeleton } from '@/pages/user/dashboard/kpi-cards';
import { TopDeliveredItemsSkeleton } from '@/pages/user/dashboard/top-delivered-items';
import { dashboard as executiveDashboard } from '@/routes/executive';
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
    DashboardSummary,
    DeliveredItemsChartPoint,
    DemographicCount,
    RequestStatusChartPoint,
    RequestsTrendPoint,
    TopBarangayPoint,
    UnspscReleasedChartPoint,
} from '@/types/dashboard';

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

const ProgramsTable = lazy(() =>
    import('@/pages/executive/dashboard/programs-table').then((module) => ({
        default: module.ProgramsTable,
    })),
);

const RequestsTrendChart = lazy(() =>
    import('@/pages/user/dashboard/requests-trend-chart').then((module) => ({
        default: module.RequestsTrendChart,
    })),
);

const BeneficiaryBreakdown = lazy(() =>
    import('@/pages/user/dashboard/demographics-panel').then((module) => ({
        default: module.BeneficiaryBreakdown,
    })),
);

const DemographicsPanel = lazy(() =>
    import('@/pages/user/dashboard/demographics-panel').then((module) => ({
        default: module.DemographicsPanel,
    })),
);

const InsightsPanel = lazy(() =>
    import('@/pages/user/dashboard/insights-panel').then((module) => ({
        default: module.InsightsPanel,
    })),
);

type DashboardPageProps = {
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
    filterOptions?: DashboardFilterOptions;
    filters?: DashboardFilters & { department: string[] };
};

function OverviewHero({
    summary,
    deliveredItemsChart,
}: {
    summary: DashboardSummary;
    deliveredItemsChart: DeliveredItemsChartPoint[];
}) {
    return (
        <Card
            className={cn(dashboardSurfaceClass, 'gap-4')}
            data-tour="dashboard-assistance-overview"
        >
            <CardHeader className="flex flex-row items-start justify-between gap-2">
                <div className="min-w-0 space-y-1">
                    <CardTitle className={dashboardTitleClass}>
                        Assistance overview
                    </CardTitle>
                    <CardDescription className={dashboardMutedClass}>
                        Request counts and the items handed over most often,
                        using the filters above.
                    </CardDescription>
                </div>
                <DashboardIconWell icon={LayoutDashboard} tone="info" />
            </CardHeader>
            <CardContent className="space-y-4">
                <KpiCards summary={summary} variant="strip" />
                <Separator />
                <div className="min-w-0">
                    <p className="mb-3 text-sm font-semibold text-slate-900 dark:text-foreground">
                        Top delivered items
                    </p>
                    <Suspense fallback={<ChartSkeleton />}>
                        <DeliveredItemsChart data={deliveredItemsChart} />
                    </Suspense>
                </div>
            </CardContent>
        </Card>
    );
}

function OverviewHeroSkeleton() {
    return (
        <Card
            className={cn(dashboardSurfaceClass, 'gap-4')}
            aria-busy="true"
            aria-label="Loading assistance overview"
        >
            <CardHeader className="flex flex-row items-start justify-between gap-2">
                <div className="space-y-2">
                    <div className="h-5 w-40 animate-pulse rounded bg-muted" />
                    <div className="h-4 w-64 animate-pulse rounded bg-muted" />
                </div>
                <div className="size-9 animate-pulse rounded-lg bg-slate-100 dark:bg-muted" />
            </CardHeader>
            <CardContent className="space-y-6">
                <KpiCardsSkeleton variant="strip" />
                <Separator />
                <ChartSkeleton />
            </CardContent>
        </Card>
    );
}

export default function ExecutiveDashboardIndex({
    summary,
    requestStatusChart,
    deliveredItemsChart,
    unspscReleasedChart,
    beneficiaryTypeChart,
    demographics,
    requestsTrend,
    insights,
    topBarangays,
    modeOfRequestChart,
    programsTable,
    filterOptions,
    filters,
}: DashboardPageProps) {
    useEffect(() => {
        setLayoutProps({
            breadcrumbs: [
                {
                    title: 'Executive',
                    href: executiveDashboard(),
                },
                {
                    title: 'Dashboard',
                    href: executiveDashboard(),
                },
            ] satisfies BreadcrumbItem[],
        });
    }, []);

    return (
        <>
            <Head title="Executive dashboard" />
            <div className={dashboardPageClass}>
                <div className="flex flex-col gap-1">
                    <h1 className={dashboardHeadingClass}>Dashboard</h1>
                    <p className={dashboardSubheadingClass}>
                        Requests, deliveries, and programs across all
                        departments.
                    </p>
                </div>

                <WhenVisible
                    data={['filterOptions', 'filters']}
                    buffer={200}
                    fallback={<DashboardFiltersSkeleton />}
                >
                    {filterOptions && filters ? (
                        <DashboardFiltersBar
                            filters={filters}
                            filterOptions={filterOptions}
                        />
                    ) : (
                        <DashboardFiltersSkeleton />
                    )}
                </WhenVisible>

                <Tabs defaultValue="overview" className="gap-4">
                    <div className="-mb-1 w-full overflow-x-auto pb-1">
                        <TabsList
                            variant="line"
                            className={dashboardTabListClass}
                        >
                            <TabsTrigger
                                value="overview"
                                className={dashboardTabTriggerClass}
                            >
                                Overview
                            </TabsTrigger>
                            <TabsTrigger
                                value="insights"
                                className={dashboardTabTriggerClass}
                            >
                                Insights
                            </TabsTrigger>
                            <TabsTrigger
                                value="beneficiaries"
                                className={dashboardTabTriggerClass}
                            >
                                Beneficiaries
                            </TabsTrigger>
                            <TabsTrigger
                                value="demographics"
                                className={dashboardTabTriggerClass}
                            >
                                Demographics
                            </TabsTrigger>
                            <TabsTrigger
                                value="programs"
                                className={dashboardTabTriggerClass}
                            >
                                Programs
                            </TabsTrigger>
                        </TabsList>
                    </div>

                    <TabsContent value="overview" className="space-y-4">
                        <div className="grid items-start gap-4 lg:grid-cols-3">
                            <div className="min-w-0 lg:col-span-2">
                                <WhenVisible
                                    data={[
                                        'summary',
                                        ...DASHBOARD_CHART_DEFER_PROPS,
                                    ]}
                                    buffer={200}
                                    fallback={<OverviewHeroSkeleton />}
                                >
                                    {summary ? (
                                        <OverviewHero
                                            summary={summary}
                                            deliveredItemsChart={
                                                deliveredItemsChart ?? []
                                            }
                                        />
                                    ) : (
                                        <OverviewHeroSkeleton />
                                    )}
                                </WhenVisible>
                            </div>
                            <div className="min-w-0">
                                <WhenVisible
                                    data="summary"
                                    buffer={200}
                                    fallback={<DashboardHighlightsSkeleton />}
                                >
                                    {summary ? (
                                        <DashboardHighlights
                                            summary={summary}
                                        />
                                    ) : (
                                        <DashboardHighlightsSkeleton />
                                    )}
                                </WhenVisible>
                            </div>
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

                        <div className="grid items-start gap-4 lg:grid-cols-2">
                            <div className="min-w-0">
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
                            <div className="min-w-0">
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
                            <Card className={dashboardSurfaceClass}>
                                <CardHeader>
                                    <CardTitle className={dashboardTitleClass}>
                                        Released by UNSPSC segment
                                    </CardTitle>
                                    <CardDescription
                                        className={dashboardMutedClass}
                                    >
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

                    <TabsContent value="insights" className="space-y-4">
                        <WhenVisible
                            data={[...DASHBOARD_INSIGHT_DEFER_PROPS]}
                            buffer={200}
                            fallback={<InsightsPanelSkeleton />}
                        >
                            <Suspense fallback={<InsightsPanelSkeleton />}>
                                {insights ? (
                                    <InsightsPanel
                                        insights={insights}
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
                    </TabsContent>

                    <TabsContent value="beneficiaries" className="space-y-4">
                        <WhenVisible
                            data="summary"
                            buffer={200}
                            fallback={<KpiCardsSkeleton />}
                        >
                            {summary ? (
                                <div className="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
                                    <DashboardStatCard
                                        label="Unique beneficiaries"
                                        value={summary.unique_beneficiaries.toLocaleString()}
                                        icon={Users}
                                        tone="info"
                                        valueClassName="text-3xl"
                                    />
                                    <DashboardStatCard
                                        label="Repeat beneficiaries"
                                        value={summary.repeat_beneficiaries.toLocaleString()}
                                        description="More than one request"
                                        icon={Repeat}
                                        tone="info"
                                        valueClassName="text-3xl"
                                    />
                                    <DashboardStatCard
                                        label="One-time beneficiaries"
                                        value={summary.one_time_beneficiaries.toLocaleString()}
                                        icon={UserRound}
                                        tone="info"
                                        valueClassName="text-3xl"
                                    />
                                    <DashboardStatCard
                                        label="Avg requests / person"
                                        value={summary.avg_requests_per_beneficiary.toFixed(
                                            2,
                                        )}
                                        icon={ClipboardList}
                                        tone="info"
                                        valueClassName="text-3xl"
                                    />
                                </div>
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
                                    <BeneficiaryBreakdown
                                        data={beneficiaryTypeChart ?? []}
                                    />
                                </Suspense>
                            </WhenVisible>
                            <WhenVisible
                                data="summary"
                                buffer={200}
                                fallback={<DeliverySnapshotSkeleton />}
                            >
                                {summary ? (
                                    <DeliverySnapshot summary={summary} />
                                ) : (
                                    <DeliverySnapshotSkeleton />
                                )}
                            </WhenVisible>
                        </div>
                    </TabsContent>

                    <TabsContent value="demographics" className="space-y-4">
                        <WhenVisible
                            data="demographics"
                            buffer={200}
                            fallback={<DemographicsPanelSkeleton />}
                        >
                            <Suspense fallback={<DemographicsPanelSkeleton />}>
                                {demographics ? (
                                    <DemographicsPanel
                                        demographics={demographics}
                                    />
                                ) : (
                                    <DemographicsPanelSkeleton />
                                )}
                            </Suspense>
                        </WhenVisible>
                    </TabsContent>

                    <TabsContent value="programs" className="space-y-4">
                        <WhenVisible
                            data="summary"
                            buffer={200}
                            fallback={<KpiCardsSkeleton variant="strip" />}
                        >
                            {summary ? (
                                <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                                    <DashboardStatCard
                                        label="Active programs"
                                        value={summary.active_programs.toLocaleString()}
                                        icon={FolderKanban}
                                        tone="success"
                                        valueClassName="text-3xl"
                                    />
                                    <DashboardStatCard
                                        label="Closed programs"
                                        value={summary.closed_programs.toLocaleString()}
                                        icon={FolderLock}
                                        tone="info"
                                        valueClassName="text-3xl"
                                    />
                                    <DashboardStatCard
                                        label="Delivered items"
                                        value={summary.total_delivered_items.toLocaleString()}
                                        icon={Package}
                                        tone="success"
                                        valueClassName="text-3xl"
                                    />
                                    <DashboardStatCard
                                        label="Denied requests"
                                        value={summary.denied_requests.toLocaleString()}
                                        icon={Ban}
                                        tone="danger"
                                        valueClassName="text-3xl"
                                    />
                                </div>
                            ) : (
                                <KpiCardsSkeleton />
                            )}
                        </WhenVisible>

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
                                <ProgramsTable data={programsTable ?? []} />
                            </Suspense>
                        </WhenVisible>
                    </TabsContent>
                </Tabs>
            </div>
        </>
    );
}
