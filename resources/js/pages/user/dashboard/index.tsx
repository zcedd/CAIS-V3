import { Head, setLayoutProps, WhenVisible } from '@inertiajs/react';
import { Ban, FolderKanban, FolderLock, Package } from 'lucide-react';
import { lazy, Suspense, useEffect } from 'react';
import { DataTableSkeleton } from '@/components/data-table/data-table-skeleton';
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
import { index as departmentDashboardIndex } from '@/routes/user/dashboard';
import type { BreadcrumbItem } from '@/types';
import { DASHBOARD_INSIGHT_DEFER_PROPS } from '@/types/dashboard';
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
    cityChart?: CityChartPoint[];
    municipalityMap?: MunicipalityMapPoint[];
    filterOptions?: DashboardFilterOptions;
    filters?: DashboardFilters;
};

export default function UserDashboardIndex({
    department,
    summary,
    demographics,
    insights,
    topBarangays,
    modeOfRequestChart,
    programsTable,
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
            <Head title={`Dashboard: ${department.name}`} />
            <div className="flex h-full min-w-0 flex-1 flex-col gap-4 overflow-x-hidden rounded-xl p-4">
                <div className="flex flex-col gap-2 sm:flex-row sm:items-end sm:justify-between">
                    <div>
                        <h1 className="text-2xl font-semibold tracking-tight">
                            Dashboard
                        </h1>
                        <p className="text-sm text-muted-foreground">
                            Requests, deliveries, and programs for{' '}
                            {department.name}
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
                                Charts & Metrics
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
                    </TabsContent>

                    <TabsContent value="insights" className="space-y-4">
                        <div data-tour="dashboard-insights">
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
