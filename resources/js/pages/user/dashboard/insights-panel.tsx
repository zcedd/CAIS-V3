import {
    Ban,
    Clock3,
    MapPin,
    PackageCheck,
    PackageX,
    Repeat,
    TrendingUp,
} from 'lucide-react';
import { Progress } from '@/components/ui/progress';
import { StatCard, StatCardSkeleton } from '@/components/user/stat-card';
import { EMPTY_CELL } from '@/lib/empty-cell';
import {
    ColumnBreakdownChart,
    DashboardChartCard,
    DashboardChartCardSkeleton,
    DonutBreakdownChart,
    ProgramOutcomesChart,
    RankedBarChart,
    sortByLabelOrder,
} from '@/pages/user/dashboard/dashboard-charts';
import type {
    DashboardInsights,
    DashboardProgramRow,
    DashboardSummary,
    DemographicCount,
    TopBarangayPoint,
} from '@/types/dashboard';

const BACKLOG_ORDER = [
    '0-7 days',
    '8-30 days',
    '31-90 days',
    '90+ days',
    'No request date',
] as const;

type InsightsPanelProps = {
    insights: DashboardInsights;
    summary: DashboardSummary;
    programs: DashboardProgramRow[];
    topBarangays: TopBarangayPoint[];
    modeOfRequestChart: DemographicCount[];
};

function formatPercent(value: number): string {
    return `${value.toFixed(1)}%`;
}

function share(numerator: number, denominator: number): number {
    if (denominator === 0) {
        return 0;
    }

    return (numerator / denominator) * 100;
}

export function InsightsPanel({
    insights,
    summary,
    programs,
    topBarangays,
    modeOfRequestChart,
}: InsightsPanelProps) {
    const itemTotal = insights.pending_items + insights.received_items;
    const receivedRate =
        itemTotal > 0 ? (insights.received_items / itemTotal) * 100 : 0;
    const backlogTotal = insights.backlog_aging.reduce(
        (sum, row) => sum + row.count,
        0,
    );
    const barangays: DemographicCount[] = topBarangays.map((row) => ({
        label: row.barangay,
        count: row.count,
    }));
    const deliveryRate = share(
        summary.delivered_requests,
        summary.total_requests,
    );
    const denialRate = share(summary.denied_requests, summary.total_requests);

    return (
        <div className="flex flex-col gap-4" data-tour="dashboard-insights">
            <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                <StatCard
                    label="Delivery rate"
                    value={formatPercent(deliveryRate)}
                    description={`${summary.delivered_requests.toLocaleString()} of ${summary.total_requests.toLocaleString()} requests`}
                    icon={TrendingUp}
                    footer={<Progress value={deliveryRate} />}
                />
                <StatCard
                    label="Denial rate"
                    value={formatPercent(denialRate)}
                    description={`${summary.denied_requests.toLocaleString()} denied requests`}
                    icon={Ban}
                />
                <StatCard
                    label="Avg days to deliver"
                    value={
                        summary.avg_days_to_deliver !== null
                            ? summary.avg_days_to_deliver.toFixed(1)
                            : EMPTY_CELL
                    }
                    description={
                        <>
                            Verify avg:{' '}
                            {summary.avg_days_to_verify !== null
                                ? `${summary.avg_days_to_verify.toFixed(1)}d`
                                : EMPTY_CELL}
                        </>
                    }
                    icon={Clock3}
                />
                <StatCard
                    label="Repeat beneficiaries"
                    value={summary.repeat_beneficiaries.toLocaleString()}
                    description={`${summary.avg_requests_per_beneficiary.toFixed(2)} requests per beneficiary`}
                    icon={Repeat}
                />
            </div>

            <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                <StatCard
                    label="Pending items"
                    value={insights.pending_items.toLocaleString()}
                    description="Not yet marked received"
                    icon={PackageX}
                />
                <StatCard
                    label="Received items"
                    value={insights.received_items.toLocaleString()}
                    description={`${receivedRate.toFixed(1)}% of item lines`}
                    icon={PackageCheck}
                />
                <StatCard
                    label="Open backlog"
                    value={backlogTotal.toLocaleString()}
                    description="In-progress requests"
                    icon={Clock3}
                />
                <StatCard
                    label="Barangays reached"
                    value={insights.distinct_barangays.toLocaleString()}
                    description="Distinct addresses in this filter"
                    icon={MapPin}
                />
            </div>

            <div className="grid items-start gap-4 lg:grid-cols-3">
                <DashboardChartCard
                    title="Backlog aging"
                    description="How long open requests have been waiting"
                >
                    <ColumnBreakdownChart
                        data={sortByLabelOrder(
                            insights.backlog_aging,
                            BACKLOG_ORDER,
                        )}
                        emptyMessage="No open backlog with these filters."
                        valueLabel="Requests"
                    />
                </DashboardChartCard>
                <DashboardChartCard
                    title="Top barangays"
                    description="Areas with the most assistance requests"
                >
                    <RankedBarChart data={barangays} valueLabel="Requests" />
                </DashboardChartCard>
                <DashboardChartCard
                    title="Mode of request"
                    description="How beneficiaries submitted their requests"
                >
                    <DonutBreakdownChart
                        data={modeOfRequestChart}
                        valueLabel="Requests"
                    />
                </DashboardChartCard>
            </div>

            <ProgramOutcomesChart data={programs} />
        </div>
    );
}

export function InsightsPanelSkeleton() {
    return (
        <div
            className="flex flex-col gap-4"
            aria-busy="true"
            aria-label="Loading activity"
        >
            <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                <StatCardSkeleton withFooter />
                {Array.from({ length: 7 }).map((_, index) => (
                    <StatCardSkeleton key={index} />
                ))}
            </div>
            <div className="grid gap-4 lg:grid-cols-3">
                {Array.from({ length: 3 }).map((_, index) => (
                    <DashboardChartCardSkeleton
                        key={index}
                        label="Loading activity"
                    />
                ))}
            </div>
            <DashboardChartCardSkeleton label="Loading program outcomes" />
        </div>
    );
}
