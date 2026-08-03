import { Skeleton } from '@/components/ui/skeleton';
import { cn } from '@/lib/utils';
import type { ProgramStatusBreakdownPoint } from '@/types/program';

type ProgramStatusBreakdownProps = {
    breakdown: ProgramStatusBreakdownPoint[];
};

const STATUS_COLORS: Record<string, string> = {
    Delivered: 'bg-emerald-500',
    Verified: 'bg-sky-500',
    Pending: 'bg-amber-500',
    Denied: 'bg-red-500',
    Closed: 'bg-slate-400',
    Unrequested: 'bg-muted-foreground/40',
};

const FALLBACK_COLORS = [
    'bg-violet-500',
    'bg-teal-500',
    'bg-orange-500',
    'bg-pink-500',
    'bg-indigo-500',
];

function colorForStatus(status: string, index: number): string {
    return (
        STATUS_COLORS[status] ?? FALLBACK_COLORS[index % FALLBACK_COLORS.length]
    );
}

export function ProgramStatusBreakdown({
    breakdown,
}: ProgramStatusBreakdownProps) {
    const total = breakdown.reduce((sum, point) => sum + point.count, 0);

    return (
        <section
            data-tour="program-status-breakdown"
            className="rounded-xl border border-border bg-card"
        >
            <div className="flex flex-col gap-3 p-4">
                <div className="space-y-1">
                    <h2 className="text-[15px] font-semibold tracking-tight">
                        Request status breakdown
                    </h2>
                    <p className="text-xs text-muted-foreground">
                        How this program&apos;s {total.toLocaleString()}{' '}
                        {total === 1 ? 'request is' : 'requests are'}{' '}
                        distributed across statuses
                    </p>
                </div>

                {total === 0 ? (
                    <p className="text-sm text-muted-foreground/60 italic">
                        No assistance requests yet
                    </p>
                ) : (
                    <>
                        <div
                            role="img"
                            aria-label="Request status distribution"
                            className="flex h-2.5 w-full gap-px overflow-hidden rounded-full bg-muted"
                        >
                            {breakdown.map((point, index) => (
                                <div
                                    key={point.status}
                                    className={cn(
                                        'h-full',
                                        colorForStatus(point.status, index),
                                    )}
                                    style={{
                                        width: `${(point.count / total) * 100}%`,
                                    }}
                                    title={`${point.status}: ${point.count.toLocaleString()}`}
                                />
                            ))}
                        </div>

                        <ul className="flex flex-wrap gap-x-5 gap-y-2">
                            {breakdown.map((point, index) => {
                                const percent = Math.round(
                                    (point.count / total) * 100,
                                );

                                return (
                                    <li
                                        key={point.status}
                                        className="flex items-center gap-1.5 text-xs"
                                    >
                                        <span
                                            aria-hidden
                                            className={cn(
                                                'size-2 shrink-0 rounded-full',
                                                colorForStatus(
                                                    point.status,
                                                    index,
                                                ),
                                            )}
                                        />
                                        <span className="text-muted-foreground">
                                            {point.status}
                                        </span>
                                        <span className="font-medium tabular-nums">
                                            {point.count.toLocaleString()}
                                        </span>
                                        <span className="tabular-nums text-muted-foreground">
                                            ({percent}%)
                                        </span>
                                    </li>
                                );
                            })}
                        </ul>
                    </>
                )}
            </div>
        </section>
    );
}

export function ProgramStatusBreakdownSkeleton() {
    return (
        <section
            className="rounded-xl border border-border bg-card"
            aria-busy="true"
            aria-label="Loading request status breakdown"
        >
            <div className="flex flex-col gap-3 p-4">
                <div className="space-y-2">
                    <Skeleton className="h-4 w-48 rounded-sm" />
                    <Skeleton className="h-3 w-64 rounded-sm" />
                </div>
                <Skeleton className="h-2.5 w-full rounded-full" />
                <div className="flex flex-wrap gap-x-5 gap-y-2">
                    {Array.from({ length: 4 }).map((_, index) => (
                        <Skeleton
                            key={index}
                            className="h-3 w-28 rounded-sm"
                        />
                    ))}
                </div>
            </div>
        </section>
    );
}
