import { Banknote, ClipboardList, Package, Wallet } from 'lucide-react';
import { Progress } from '@/components/ui/progress';
import { Skeleton } from '@/components/ui/skeleton';
import { ApprovalTrail } from '@/components/user/approval-trail-row';
import { MetricRow } from '@/components/user/metric-row';
import type { ProgramCardMetric } from '@/components/user/program-card';
import { StatCard, StatCardSkeleton } from '@/components/user/stat-card';
import { formatPeso } from '@/lib/format-peso';
import type { ProgramSummary } from '@/types/program';

function percent(part: number, total: number): number {
    if (total <= 0) {
        return 0;
    }

    return Math.round((part / total) * 100);
}

export function ProgramShowMetrics({
    metrics,
    summary,
}: {
    metrics: ProgramCardMetric;
    summary?: ProgramSummary;
}) {
    const disbursed =
        metrics.budget > 0
            ? Math.min(100, (metrics.cash_released / metrics.budget) * 100)
            : 0;
    const remaining = Math.max(metrics.budget - metrics.cash_released, 0);
    const perRequest =
        metrics.delivered_requests > 0
            ? metrics.cash_released / metrics.delivered_requests
            : null;
    const deliveredRate = percent(
        metrics.delivered_requests,
        metrics.total_requests,
    );

    return (
        <div className="grid gap-3 md:grid-cols-2 xl:grid-cols-4">
            <StatCard
                label="Allocated budget"
                value={metrics.budget > 0 ? formatPeso(metrics.budget) : '—'}
                icon={Wallet}
                footer={
                    <div className="flex flex-col gap-2">
                        <Progress value={disbursed} />
                        <MetricRow
                            label="Disbursed"
                            value={`${Math.round(disbursed)}%`}
                        />
                        <MetricRow
                            label="Remaining"
                            value={
                                metrics.budget > 0 ? formatPeso(remaining) : '—'
                            }
                        />
                    </div>
                }
            />
            <StatCard
                label="Cash released"
                value={formatPeso(metrics.cash_released)}
                icon={Banknote}
                footer={
                    <div className="flex flex-col gap-2">
                        <MetricRow
                            label="Per request"
                            value={
                                perRequest === null
                                    ? '—'
                                    : formatPeso(perRequest)
                            }
                        />
                        <MetricRow
                            label="Goods released"
                            value={metrics.goods_released.toLocaleString()}
                        />
                    </div>
                }
            />
            <StatCard
                label="Requests"
                value={metrics.total_requests.toLocaleString()}
                icon={ClipboardList}
                footer={
                    <div className="flex flex-col gap-2">
                        <Progress value={deliveredRate} />
                        <MetricRow
                            label="Delivered"
                            value={metrics.delivered_requests.toLocaleString()}
                            hint={`${deliveredRate}%`}
                        />
                        <MetricRow
                            label="In progress"
                            value={(
                                summary?.in_progress_requests ?? 0
                            ).toLocaleString()}
                        />
                    </div>
                }
            />
            <StatCard
                label="Delivered items"
                value={(
                    summary?.total_delivered_items ?? metrics.goods_released
                ).toLocaleString()}
                icon={Package}
                footer={
                    <MetricRow
                        label="Claimants"
                        value={metrics.claimants.toLocaleString()}
                    />
                }
            />
        </div>
    );
}

export function ProgramShowMetricsSkeleton() {
    return (
        <div
            className="grid gap-3 md:grid-cols-2 xl:grid-cols-4"
            aria-busy="true"
            aria-label="Loading program metrics"
        >
            {Array.from({ length: 4 }, (_, index) => (
                <StatCardSkeleton key={index} withFooter />
            ))}
        </div>
    );
}

export function ProgramReachCards({ metrics }: { metrics: ProgramCardMetric }) {
    const people = metrics.individuals;
    const female = percent(metrics.female, people);
    const male = percent(metrics.male, people);
    const other = percent(metrics.other, people);

    return (
        <>
            <section className="flex h-full flex-col gap-3 rounded-xl border border-border bg-card p-4">
                <div className="flex items-center justify-between gap-2">
                    <h2 className="text-[15px] font-semibold tracking-tight">
                        Demographic reach
                    </h2>
                    <span className="font-mono text-xs text-muted-foreground tabular-nums">
                        {people.toLocaleString()} people
                    </span>
                </div>
                <div className="grid grid-cols-2 gap-2">
                    <div className="rounded-md border border-border bg-muted/30 px-2 py-1.5">
                        <p className="text-[10px] text-muted-foreground">
                            Mean age
                        </p>
                        <p className="font-mono text-sm tabular-nums">
                            {metrics.mean_age === null
                                ? '—'
                                : `${metrics.mean_age} yrs`}
                        </p>
                    </div>
                    <div className="rounded-md border border-border bg-muted/30 px-2 py-1.5">
                        <p className="text-[10px] text-muted-foreground">PWD</p>
                        <p className="font-mono text-sm tabular-nums">
                            {percent(metrics.pwd, people)}%
                        </p>
                    </div>
                </div>
                <div
                    role="img"
                    aria-label={`${female}% female, ${male}% male, ${other}% other`}
                    className="flex h-2.5 overflow-hidden rounded-full bg-muted"
                >
                    {people > 0 ? (
                        <>
                            <div
                                className="h-full bg-emerald-500"
                                style={{ width: `${female}%` }}
                            />
                            <div
                                className="h-full bg-sky-500"
                                style={{ width: `${male}%` }}
                            />
                            <div
                                className="h-full bg-muted-foreground/40"
                                style={{ width: `${other}%` }}
                            />
                        </>
                    ) : null}
                </div>
                <p className="font-mono text-[10px] text-muted-foreground tabular-nums">
                    <span className="mr-2 inline-block size-1.5 rounded-full bg-emerald-500" />
                    {female}% F
                    <span className="mx-2 inline-block size-1.5 rounded-full bg-sky-500" />
                    {male}% M
                    <span className="mx-2 inline-block size-1.5 rounded-full bg-muted-foreground/40" />
                    {other}% other
                </p>
            </section>

            <section className="flex h-full flex-col gap-3 rounded-xl border border-border bg-card p-4">
                <h2 className="text-[15px] font-semibold tracking-tight">
                    Endorsement trail
                </h2>
                <ApprovalTrail events={metrics.approval_trail} />
            </section>
        </>
    );
}

export function ProgramReachSkeleton() {
    return (
        <>
            <section
                className="flex h-full flex-col gap-3 rounded-xl border border-border bg-card p-4"
                aria-busy="true"
                aria-label="Loading demographic reach"
            >
                <div className="flex items-center justify-between gap-2">
                    <Skeleton className="h-4 w-36" />
                    <Skeleton className="h-3 w-16" />
                </div>
                <div className="grid grid-cols-2 gap-2">
                    <Skeleton className="h-12" />
                    <Skeleton className="h-12" />
                </div>
                <Skeleton className="h-2.5 w-full rounded-full" />
                <Skeleton className="h-3 w-40" />
            </section>
            <section
                className="flex h-full flex-col gap-3 rounded-xl border border-border bg-card p-4"
                aria-busy="true"
                aria-label="Loading endorsement trail"
            >
                <Skeleton className="h-4 w-36" />
                <Skeleton className="h-8 w-full" />
                <Skeleton className="h-8 w-full" />
            </section>
        </>
    );
}
