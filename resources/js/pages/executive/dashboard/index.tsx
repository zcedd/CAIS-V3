import { Head, setLayoutProps, WhenVisible } from '@inertiajs/react';
import {
    Ban,
    ClipboardList,
    FolderKanban,
    FolderLock,
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
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import { StatCard } from '@/components/user/stat-card';
import { DashboardFiltersButton } from '@/pages/executive/dashboard/dashboard-filters';
import { DashboardFiltersSkeleton } from '@/pages/user/dashboard/dashboard-filters-skeleton';
import {
    DeliverySnapshot,
    DeliverySnapshotSkeleton,
} from '@/pages/user/dashboard/dashboard-highlights';
import { DemographicsPanelSkeleton } from '@/pages/user/dashboard/demographics-panel';
import { InsightsPanelSkeleton } from '@/pages/user/dashboard/insights-panel';
import {
    IntelligencePanel,
    IntelligencePanelSkeleton,
} from '@/pages/user/dashboard/intelligence-panel';
import { KpiCardsSkeleton } from '@/pages/user/dashboard/kpi-cards';
import { MunicipalityMapSkeleton } from '@/pages/user/dashboard/municipality-map-skeleton';
import { TopDeliveredItemsSkeleton } from '@/pages/user/dashboard/top-delivered-items';
import { dashboard as administratorDashboard } from '@/routes/administrator';
import { dashboard as executiveDashboard } from '@/routes/executive';
import type { BreadcrumbItem } from '@/types';
import {
    DASHBOARD_CHART_DEFER_PROPS,
    DASHBOARD_INSIGHT_DEFER_PROPS,
} from '@/types/dashboard';
import type {
    BeneficiaryTypeChartPoint,
    CityChartPoint,
    DashboardDemographics,
    DashboardFilterOption,
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
    MunicipalityMapPoint,
} from '@/types/dashboard';

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

const MunicipalityMap = lazy(() =>
    import('@/pages/user/dashboard/municipality-map').then((module) => ({
        default: module.MunicipalityMap,
    })),
);

type DashboardPageProps = {
    audience?: 'executive' | 'administrator';
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
    cityChart?: CityChartPoint[];
    municipalityMap?: MunicipalityMapPoint[];
    programsTable?: DashboardProgramRow[];
    filterOptions?: DashboardFilterOptions & {
        departments: DashboardFilterOption[];
    };
    filters?: DashboardFilters & { department: string[] };
};

export default function ExecutiveDashboardIndex({
    audience = 'executive',
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
    cityChart,
    municipalityMap,
    programsTable,
    filterOptions,
    filters,
}: DashboardPageProps) {
    const dashboard =
        audience === 'administrator' ? administratorDashboard : executiveDashboard;
    const sectionTitle =
        audience === 'administrator' ? 'Administrator' : 'Executive';

    useEffect(() => {
        setLayoutProps({
            breadcrumbs: [
                {
                    title: sectionTitle,
                    href: dashboard(),
                },
                {
                    title: 'Dashboard',
                    href: dashboard(),
                },
            ] satisfies BreadcrumbItem[],
        });
    }, [dashboard, sectionTitle]);

    return (
        <>
            <Head title={`${sectionTitle} dashboard`} />
            <div className="flex h-full min-w-0 flex-1 flex-col gap-4 overflow-x-hidden rounded-xl p-4">
                <div className="flex flex-col gap-2 sm:flex-row sm:items-end sm:justify-between">
                    <div>
                        <h1 className="text-2xl font-semibold tracking-tight">
                            Dashboard
                        </h1>
                        <p className="text-sm text-muted-foreground">
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
                            <DashboardFiltersButton
                                audience={audience}
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
                                Charts & Metrics
                            </TabsTrigger>
                            <TabsTrigger
                                value="insights"
                                data-tour="dashboard-tab-insights"
                            >
                                Insights
                            </TabsTrigger>
                            <TabsTrigger
                                value="beneficiaries"
                                data-tour="dashboard-tab-beneficiaries"
                            >
                                Beneficiaries
                            </TabsTrigger>
                            <TabsTrigger
                                value="demographics"
                                data-tour="dashboard-tab-demographics"
                            >
                                Demographics
                            </TabsTrigger>
                            <TabsTrigger
                                value="programs"
                                data-tour="dashboard-tab-programs"
                            >
                                Table View
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
                    </TabsContent>

                    <TabsContent value="beneficiaries" className="space-y-4">
                        <div data-tour="dashboard-beneficiaries">
                            <WhenVisible
                                data="summary"
                                buffer={200}
                                fallback={<KpiCardsSkeleton />}
                            >
                                {summary ? (
                                    <div className="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
                                        <StatCard
                                            label="Unique beneficiaries"
                                            value={summary.unique_beneficiaries.toLocaleString()}
                                            icon={Users}
                                        />
                                        <StatCard
                                            label="Repeat beneficiaries"
                                            value={summary.repeat_beneficiaries.toLocaleString()}
                                            description="More than one request"
                                            icon={Repeat}
                                        />
                                        <StatCard
                                            label="One-time beneficiaries"
                                            value={summary.one_time_beneficiaries.toLocaleString()}
                                            icon={UserRound}
                                        />
                                        <StatCard
                                            label="Avg requests / person"
                                            value={summary.avg_requests_per_beneficiary.toFixed(
                                                2,
                                            )}
                                            icon={ClipboardList}
                                        />
                                    </div>
                                ) : (
                                    <KpiCardsSkeleton />
                                )}
                            </WhenVisible>
                        </div>

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
                            <div data-tour="dashboard-delivery-snapshot">
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
                        </div>
                    </TabsContent>

                    <TabsContent value="demographics" className="space-y-4">
                        <div data-tour="dashboard-demographics">
                            <WhenVisible
                                data="demographics"
                                buffer={200}
                                fallback={<DemographicsPanelSkeleton />}
                            >
                                <Suspense
                                    fallback={<DemographicsPanelSkeleton />}
                                >
                                    {demographics ? (
                                        <DemographicsPanel
                                            demographics={demographics}
                                        />
                                    ) : (
                                        <DemographicsPanelSkeleton />
                                    )}
                                </Suspense>
                            </WhenVisible>
                        </div>
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
                                            icon={FolderKanban}
                                        />
                                        <StatCard
                                            label="Closed programs"
                                            value={summary.closed_programs.toLocaleString()}
                                            icon={FolderLock}
                                        />
                                        <StatCard
                                            label="Delivered items"
                                            value={summary.total_delivered_items.toLocaleString()}
                                            icon={Package}
                                        />
                                        <StatCard
                                            label="Denied requests"
                                            value={summary.denied_requests.toLocaleString()}
                                            icon={Ban}
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
                                        audience={audience}
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
