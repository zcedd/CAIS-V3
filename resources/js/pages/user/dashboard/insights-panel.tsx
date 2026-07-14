import {
    Clock3,
    MapPin,
    PackageCheck,
    PackageX,
    Send,
    Timer,
} from 'lucide-react';
import type { LucideIcon } from 'lucide-react';
import { Progress } from '@/components/ui/progress';
import {
    DashboardSectionCard,
    DashboardStatCard,
    DashboardStatCardSkeleton,
} from '@/pages/user/dashboard/dashboard-stat-card';
import type {
    DashboardInsights,
    DemographicCount,
    TopBarangayPoint,
} from '@/types/dashboard';

function BreakdownList({
    title,
    description,
    data,
    icon,
    emptyMessage = 'No data for the selected filters.',
}: {
    title: string;
    description: string;
    data: DemographicCount[];
    icon: LucideIcon;
    emptyMessage?: string;
}) {
    const total = data.reduce((sum, row) => sum + row.count, 0);

    return (
        <DashboardSectionCard
            title={title}
            description={description}
            icon={icon}
        >
            {data.length === 0 ? (
                <p className="py-6 text-center text-sm text-muted-foreground">
                    {emptyMessage}
                </p>
            ) : (
                <ul className="space-y-3">
                    {data.map((row) => {
                        const percent =
                            total > 0 ? (row.count / total) * 100 : 0;

                        return (
                            <li key={row.label} className="space-y-1.5">
                                <div className="flex items-center justify-between gap-2 text-sm">
                                    <span className="truncate font-medium">
                                        {row.label}
                                    </span>
                                    <span className="shrink-0 tabular-nums text-muted-foreground">
                                        {row.count.toLocaleString()} (
                                        {percent.toFixed(1)}%)
                                    </span>
                                </div>
                                <Progress value={percent} className="h-1.5" />
                            </li>
                        );
                    })}
                </ul>
            )}
        </DashboardSectionCard>
    );
}

type InsightsPanelProps = {
    insights: DashboardInsights;
    topBarangays: TopBarangayPoint[];
    modeOfRequestChart: DemographicCount[];
};

export function InsightsPanel({
    insights,
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

    return (
        <div className="space-y-4" data-tour="dashboard-insights">
            <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                <DashboardStatCard
                    label="Pending items"
                    value={insights.pending_items.toLocaleString()}
                    description="Not yet marked received"
                    icon={PackageX}
                />
                <DashboardStatCard
                    label="Received items"
                    value={insights.received_items.toLocaleString()}
                    description={`${receivedRate.toFixed(1)}% of item lines`}
                    icon={PackageCheck}
                />
                <DashboardStatCard
                    label="Open backlog"
                    value={backlogTotal.toLocaleString()}
                    description="In-progress requests"
                    icon={Clock3}
                />
                <DashboardStatCard
                    label="Barangays reached"
                    value={insights.distinct_barangays.toLocaleString()}
                    description="Distinct addresses in scope"
                    icon={MapPin}
                />
            </div>

            <div className="grid items-start gap-4 lg:grid-cols-3">
                <BreakdownList
                    title="Backlog aging"
                    description="How long open requests have been waiting"
                    data={insights.backlog_aging}
                    icon={Timer}
                    emptyMessage="No open backlog for the selected filters."
                />
                <BreakdownList
                    title="Top barangays"
                    description="Areas with the most assistance requests"
                    data={topBarangays.map((row) => ({
                        label: row.barangay,
                        count: row.count,
                    }))}
                    icon={MapPin}
                />
                <BreakdownList
                    title="Mode of request"
                    description="How beneficiaries submitted their requests"
                    data={modeOfRequestChart}
                    icon={Send}
                />
            </div>
        </div>
    );
}

export function InsightsPanelSkeleton() {
    return (
        <div
            className="space-y-4"
            aria-busy="true"
            aria-label="Loading insights"
        >
            <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                {Array.from({ length: 4 }).map((_, index) => (
                    <DashboardStatCardSkeleton key={index} />
                ))}
            </div>
            <div className="grid gap-4 lg:grid-cols-3">
                {Array.from({ length: 3 }).map((_, index) => (
                    <div
                        key={index}
                        className="overflow-hidden rounded-2xl bg-card ring-1 ring-foreground/10"
                    >
                        <div className="flex items-start justify-between gap-2 px-6 pt-6">
                            <div className="space-y-2">
                                <div className="h-5 w-32 animate-pulse rounded bg-muted" />
                                <div className="h-4 w-48 animate-pulse rounded bg-muted" />
                            </div>
                            <div className="size-5 animate-pulse rounded bg-muted" />
                        </div>
                        <div className="space-y-3 px-6 py-6">
                            {Array.from({ length: 4 }).map((__, row) => (
                                <div key={row} className="space-y-1.5">
                                    <div className="h-4 w-full animate-pulse rounded bg-muted" />
                                    <div className="h-1.5 w-full animate-pulse rounded bg-muted" />
                                </div>
                            ))}
                        </div>
                    </div>
                ))}
            </div>
        </div>
    );
}
