import {
    Activity,
    Clock3,
    Package,
    Repeat,
    TrendingUp,
    Users,
} from 'lucide-react';
import { Progress } from '@/components/ui/progress';
import {
    DashboardSectionCard,
    DashboardStatCard,
    DashboardStatCardSkeleton,
} from '@/pages/user/dashboard/dashboard-stat-card';
import type { DashboardSummary } from '@/types/dashboard';
import { EMPTY_CELL } from '@/lib/empty-cell';

type DashboardHighlightsProps = {
    summary: DashboardSummary;
};

function formatPercent(value: number): string {
    return `${value.toFixed(1)}%`;
}

function deliveryRate(summary: DashboardSummary): number {
    if (summary.total_requests === 0) {
        return 0;
    }

    return (summary.delivered_requests / summary.total_requests) * 100;
}

function RadialRate({
    label,
    display,
    progress,
    description,
}: {
    label: string;
    display: string;
    progress: number;
    description: string;
}) {
    const clamped = Math.min(100, Math.max(0, progress));
    const radius = 36;
    const circumference = 2 * Math.PI * radius;
    const offset = circumference - (clamped / 100) * circumference;

    return (
        <div className="flex items-center gap-3">
            <div className="relative size-20 shrink-0">
                <svg className="size-20 -rotate-90" viewBox="0 0 80 80">
                    <circle
                        cx="40"
                        cy="40"
                        r={radius}
                        fill="none"
                        stroke="currentColor"
                        strokeWidth="6"
                        className="text-muted"
                    />
                    <circle
                        cx="40"
                        cy="40"
                        r={radius}
                        fill="none"
                        stroke="currentColor"
                        strokeWidth="6"
                        strokeDasharray={circumference}
                        strokeDashoffset={offset}
                        strokeLinecap="round"
                        className="text-primary transition-all"
                    />
                </svg>
                <span className="absolute inset-0 flex items-center justify-center text-xs font-semibold tabular-nums">
                    {display}
                </span>
            </div>
            <div className="min-w-0 space-y-0.5">
                <p className="text-sm font-medium">{label}</p>
                <p className="text-xs text-muted-foreground">{description}</p>
            </div>
        </div>
    );
}

export function DashboardHighlights({ summary }: DashboardHighlightsProps) {
    const rate = deliveryRate(summary);

    return (
        <div className="flex flex-col gap-4" data-tour="dashboard-highlights">
            <DashboardStatCard
                label="Delivery rate"
                value={formatPercent(rate)}
                description={`${summary.delivered_requests.toLocaleString()} of ${summary.total_requests.toLocaleString()} requests`}
                icon={TrendingUp}
                valueClassName="text-3xl"
                footer={<Progress value={rate} />}
            />
            <DashboardStatCard
                label="In progress"
                value={summary.in_progress_requests.toLocaleString()}
                description={`${summary.denied_requests.toLocaleString()} denied · ${summary.unique_beneficiaries.toLocaleString()} beneficiaries`}
                icon={Activity}
                valueClassName="text-3xl"
            />
            <DashboardStatCard
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
                valueClassName="text-3xl"
            />
        </div>
    );
}

export function DeliverySnapshot({
    summary,
}: {
    summary: DashboardSummary;
}) {
    const rate = deliveryRate(summary);
    const avgItems =
        summary.delivered_requests > 0
            ? summary.total_delivered_items / summary.delivered_requests
            : 0;
    const avgRingProgress = Math.min(100, avgItems * 10);

    return (
        <DashboardSectionCard
            title="Delivery snapshot"
            description="How much of the filtered work is done"
            icon={Package}
            data-tour="dashboard-delivery-snapshot"
            contentClassName="space-y-6"
        >
            <div className="grid gap-3">
                <div className="flex items-center justify-between gap-2 text-sm">
                    <span className="text-muted-foreground">
                        Delivered requests
                    </span>
                    <span className="font-medium tabular-nums">
                        {summary.delivered_requests.toLocaleString()}
                    </span>
                </div>
                <div className="flex items-center justify-between gap-2 text-sm">
                    <span className="text-muted-foreground">
                        Delivered items
                    </span>
                    <span className="font-medium tabular-nums">
                        {summary.total_delivered_items.toLocaleString()}
                    </span>
                </div>
                <div className="flex items-center justify-between gap-2 text-sm">
                    <span className="text-muted-foreground">
                        Active programs
                    </span>
                    <span className="font-medium tabular-nums">
                        {summary.active_programs.toLocaleString()}
                    </span>
                </div>
                <div className="flex items-center justify-between gap-2 text-sm">
                    <span className="flex items-center gap-1.5 text-muted-foreground">
                        <Users className="size-3.5" />
                        Requests / beneficiary
                    </span>
                    <span className="font-medium tabular-nums">
                        {summary.avg_requests_per_beneficiary.toFixed(2)}
                    </span>
                </div>
                <div className="flex items-center justify-between gap-2 text-sm">
                    <span className="flex items-center gap-1.5 text-muted-foreground">
                        <Repeat className="size-3.5" />
                        Repeat beneficiaries
                    </span>
                    <span className="font-medium tabular-nums">
                        {summary.repeat_beneficiaries.toLocaleString()}
                    </span>
                </div>
            </div>

            <div className="space-y-4 border-t pt-4">
                <RadialRate
                    label="% Delivered"
                    display={formatPercent(rate)}
                    progress={rate}
                    description="Share of requests delivered"
                />
                <RadialRate
                    label="Items / delivery"
                    display={avgItems.toFixed(1)}
                    progress={avgRingProgress}
                    description={
                        summary.delivered_requests > 0
                            ? 'Average items per delivered request'
                            : 'No deliveries in this filter'
                    }
                />
            </div>
        </DashboardSectionCard>
    );
}

export function DashboardHighlightsSkeleton() {
    return (
        <div
            className="flex flex-col gap-4"
            aria-busy="true"
            aria-label="Loading highlights"
        >
            <DashboardStatCardSkeleton withFooter />
            <DashboardStatCardSkeleton />
            <DashboardStatCardSkeleton />
        </div>
    );
}

export function DeliverySnapshotSkeleton() {
    return (
        <div
            className="overflow-hidden rounded-2xl bg-card ring-1 ring-foreground/10"
            aria-busy="true"
            aria-label="Loading snapshot"
        >
            <div className="flex items-start justify-between gap-2 px-6 pt-6">
                <div className="space-y-2">
                    <div className="h-5 w-36 animate-pulse rounded bg-muted" />
                    <div className="h-4 w-48 animate-pulse rounded bg-muted" />
                </div>
                <div className="size-5 animate-pulse rounded bg-muted" />
            </div>
            <div className="space-y-4 px-6 py-6">
                {[0, 1, 2].map((key) => (
                    <div
                        key={key}
                        className="h-4 w-full animate-pulse rounded bg-muted"
                    />
                ))}
                <div className="h-20 w-full animate-pulse rounded bg-muted" />
            </div>
        </div>
    );
}
