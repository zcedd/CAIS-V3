import {
    Activity,
    Clock3,
    Package,
    Repeat,
    TrendingUp,
    Users,
} from 'lucide-react';
import { Progress } from '@/components/ui/progress';
import { Skeleton } from '@/components/ui/skeleton';
import { StatCard, StatCardSkeleton } from '@/components/user/stat-card';
import { EMPTY_CELL } from '@/lib/empty-cell';
import {
    DashboardSectionCard,
    SectionCardSkeleton,
} from '@/pages/user/dashboard/dashboard-stat-card';
import type { DashboardSummary } from '@/types/dashboard';

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
            <StatCard
                label="Delivery rate"
                value={formatPercent(rate)}
                description={`${summary.delivered_requests.toLocaleString()} of ${summary.total_requests.toLocaleString()} requests`}
                icon={TrendingUp}
                footer={<Progress value={rate} />}
            />
            <StatCard
                label="In progress"
                value={summary.in_progress_requests.toLocaleString()}
                description={`${summary.denied_requests.toLocaleString()} denied · ${summary.unique_beneficiaries.toLocaleString()} beneficiaries`}
                icon={Activity}
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
            <StatCardSkeleton withFooter />
            <StatCardSkeleton />
            <StatCardSkeleton />
        </div>
    );
}

export function DeliverySnapshotSkeleton() {
    return (
        <SectionCardSkeleton label="Loading snapshot" titleClassName="w-36">
            <div className="space-y-4">
                {[0, 1, 2].map((key) => (
                    <Skeleton key={key} className="h-4 w-full" />
                ))}
                <Skeleton className="h-20 w-full" />
            </div>
        </SectionCardSkeleton>
    );
}
