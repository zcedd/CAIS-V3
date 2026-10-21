import { Link } from '@inertiajs/react';
import { BarChart3 } from 'lucide-react';
import type { ReactNode } from 'react';
import { Button } from '@/components/ui/button';
import { Progress } from '@/components/ui/progress';
import { Skeleton } from '@/components/ui/skeleton';
import {
    Tooltip,
    TooltipContent,
    TooltipTrigger,
} from '@/components/ui/tooltip';
import { ApprovalBadge } from '@/components/user/approval-badge';
import { ApprovalTrail, type ApprovalTrailEvent } from '@/components/user/approval-trail-row';
import { CodeChip } from '@/components/user/code-chip';
import { MetricRow } from '@/components/user/metric-row';
import { formatPeso } from '@/lib/format-peso';
import { cn } from '@/lib/utils';
import type { ProgramListRow } from '@/pages/user/programs/program-folder-card';

export type ProgramCardMetric = {
    budget: number;
    cash_released: number;
    goods_released: number;
    item_kinds: string[];
    total_requests: number;
    delivered_requests: number;
    claimants: number;
    mean_age: number | null;
    female: number;
    male: number;
    other: number;
    pwd: number;
    individuals: number;
    submitted_at: string | null;
    approved_at: string | null;
    approval_trail: ApprovalTrailEvent[];
};

export type ProgramCardModel = ProgramListRow & {
    approval_status?: string | null;
    batch_name?: string | null;
};

function kindLabel(kinds: string[]): string {
    const hasCash = kinds.includes('cash');
    const hasGoods = kinds.includes('goods') || kinds.includes('service');

    if (hasCash && hasGoods) {
        return 'Hybrid';
    }

    if (hasCash) {
        return 'Cash';
    }

    if (kinds.includes('goods')) {
        return 'In-Kind';
    }

    if (kinds.includes('service')) {
        return 'Service';
    }

    return 'Program';
}

function percent(part: number, total: number): number {
    if (total <= 0) {
        return 0;
    }

    return Math.round((part / total) * 100);
}

export function ProgramCard({
    program,
    metrics,
    onInspect,
    href,
    actions,
    className,
}: {
    program: ProgramCardModel;
    metrics?: ProgramCardMetric | null;
    onInspect?: () => void;
    href?: string;
    actions?: ReactNode;
    className?: string;
}) {
    const card = metrics ?? {
        budget: 0,
        cash_released: 0,
        goods_released: 0,
        item_kinds: [],
        total_requests: 0,
        delivered_requests: 0,
        claimants: 0,
        mean_age: null,
        female: 0,
        male: 0,
        other: 0,
        pwd: 0,
        individuals: 0,
        submitted_at: null,
        approved_at: null,
        approval_trail: [],
    };
    const progress =
        card.budget > 0
            ? Math.min(100, (card.cash_released / card.budget) * 100)
            : card.total_requests > 0
              ? (card.delivered_requests / card.total_requests) * 100
              : 0;
    const perRequest =
        card.delivered_requests > 0
            ? card.cash_released / card.delivered_requests
            : null;
    const code = program.batch_name?.trim()
        ? program.batch_name
        : `PRG-${String(program.id).padStart(4, '0')}`;
    const isClosed = Boolean(program.is_closed);
    const description = program.descriptions?.trim();
    const female = percent(card.female, card.individuals);
    const male = percent(card.male, card.individuals);
    const other = percent(card.other, card.individuals);

    return (
        <article
            className={cn(
                'flex h-full flex-col gap-3 rounded-xl border border-border bg-card p-4 transition-colors duration-150 hover:border-foreground/25 hover:shadow-sm',
                className,
            )}
        >
            <div className="flex flex-wrap items-center gap-1.5">
                <CodeChip>{code}</CodeChip>
                <CodeChip>{kindLabel(card.item_kinds)}</CodeChip>
                <ApprovalBadge status={program.approval_status} />
                {isClosed ? <CodeChip>Closed</CodeChip> : null}
            </div>

            <div className="space-y-1">
                <h3 className="line-clamp-2 text-[15px] leading-snug font-semibold tracking-tight">
                    {href ? (
                        <Link href={href} className="hover:underline">
                            {program.name}
                        </Link>
                    ) : (
                        program.name
                    )}
                </h3>
                <p
                    className={cn(
                        'line-clamp-1 text-sm leading-relaxed',
                        description
                            ? 'text-muted-foreground'
                            : 'text-muted-foreground/60 italic',
                    )}
                >
                    {description || 'No description'}
                </p>
            </div>

            <div className="space-y-1.5">
                <MetricRow
                    label="Allocated Budget"
                    value={card.budget > 0 ? formatPeso(card.budget) : '—'}
                />
                <MetricRow
                    label="Per-Request Cash"
                    value={perRequest === null ? '—' : formatPeso(perRequest)}
                />
                <MetricRow
                    label="Disbursed Claims"
                    value={card.delivered_requests.toLocaleString()}
                    hint={`of ${card.total_requests.toLocaleString()} claimed`}
                />
            </div>

            <div className="space-y-1.5">
                <div className="flex items-center justify-between gap-3 text-xs text-muted-foreground">
                    <span>Budget disbursed</span>
                    <span className="tabular-nums">
                        {Math.round(progress)}%
                    </span>
                </div>
                <Progress value={progress} />
                <p className="text-right text-xs text-muted-foreground tabular-nums">
                    {card.budget > 0
                        ? `${formatPeso(Math.max(card.budget - card.cash_released, 0))} remaining`
                        : `${card.goods_released.toLocaleString()} in-kind released`}
                </p>
            </div>

            <div className="space-y-2 rounded-lg border border-border bg-muted/30 p-3">
                <div className="flex items-center justify-between gap-2">
                    <p className="text-xs font-medium">Demographic Reach</p>
                    <span className="font-mono text-[10px] text-muted-foreground tabular-nums">
                        {card.individuals.toLocaleString()} people
                    </span>
                </div>
                <div className="grid grid-cols-2 gap-2">
                    <div className="rounded-md border border-border bg-card px-2 py-1.5">
                        <p className="text-[10px] text-muted-foreground">
                            Mean Age
                        </p>
                        <p className="font-mono text-sm tabular-nums">
                            {card.mean_age === null
                                ? '—'
                                : `${card.mean_age} yrs`}
                        </p>
                    </div>
                    <div className="rounded-md border border-border bg-card px-2 py-1.5">
                        <p className="text-[10px] text-muted-foreground">PWD</p>
                        <p className="font-mono text-sm tabular-nums">
                            {percent(card.pwd, card.individuals)}%
                        </p>
                    </div>
                </div>
                <div
                    role="img"
                    aria-label={`${female}% female, ${male}% male, ${other}% other`}
                    className="flex h-1.5 overflow-hidden rounded-full bg-muted"
                >
                    {card.individuals > 0 ? (
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
                    <span className="mr-1.5 inline-block size-1.5 rounded-full bg-emerald-500" />
                    {female}% F
                    <span className="mx-1.5 inline-block size-1.5 rounded-full bg-sky-500" />
                    {male}% M
                    <span className="mx-1.5 inline-block size-1.5 rounded-full bg-muted-foreground/40" />
                    {other}% other
                </p>
            </div>

            <div className="space-y-2 border-t border-border pt-3">
                <ApprovalTrail
                    className="text-xs"
                    events={card.approval_trail}
                />
            </div>

            {actions || onInspect ? (
                <div
                    className={cn(
                        'mt-auto grid gap-2',
                        actions && onInspect ? 'grid-cols-8' : 'grid-cols-1',
                    )}
                >
                    {actions ? (
                        <div className={onInspect ? 'col-span-6' : undefined}>
                            {actions}
                        </div>
                    ) : null}
                    {onInspect ? (
                        actions ? (
                            <Tooltip>
                                <TooltipTrigger asChild>
                                    <Button
                                        type="button"
                                        variant="outline"
                                        className="col-span-2 w-full shrink px-0"
                                        aria-label="Inspect Program Analytics"
                                        onClick={onInspect}
                                    >
                                        <BarChart3 />
                                    </Button>
                                </TooltipTrigger>
                                <TooltipContent>
                                    Inspect Program Analytics
                                </TooltipContent>
                            </Tooltip>
                        ) : (
                            <Button
                                type="button"
                                variant="outline"
                                className="w-full"
                                onClick={onInspect}
                            >
                                <BarChart3 data-icon="inline-start" />
                                Inspect Program Analytics
                            </Button>
                        )
                    ) : null}
                </div>
            ) : null}
        </article>
    );
}

export function ProgramCardSkeleton() {
    return (
        <article
            className="flex h-full flex-col gap-3 rounded-xl border border-border bg-card p-4"
            aria-busy="true"
            aria-label="Loading program"
        >
            <div className="flex flex-wrap items-center gap-1.5">
                <Skeleton className="h-5 w-16" />
                <Skeleton className="h-5 w-14" />
                <Skeleton className="h-5 w-20" />
            </div>
            <div className="space-y-1">
                <Skeleton className="h-5 w-3/4" />
                <Skeleton className="h-4 w-full" />
            </div>
            <div className="space-y-1.5">
                {Array.from({ length: 3 }, (_, index) => (
                    <div
                        key={index}
                        className="flex items-center justify-between gap-3"
                    >
                        <Skeleton className="h-3 w-28" />
                        <Skeleton className="h-3 w-16" />
                    </div>
                ))}
            </div>
            <div className="space-y-1.5">
                <div className="flex items-center justify-between gap-3">
                    <Skeleton className="h-3 w-24" />
                    <Skeleton className="h-3 w-8" />
                </div>
                <Skeleton className="h-2 w-full rounded-full" />
                <Skeleton className="ml-auto h-3 w-28" />
            </div>
            <div className="space-y-2 rounded-lg border border-border bg-muted/30 p-3">
                <div className="flex items-center justify-between gap-2">
                    <Skeleton className="h-3 w-28" />
                    <Skeleton className="h-3 w-16" />
                </div>
                <div className="grid grid-cols-2 gap-2">
                    <Skeleton className="h-10" />
                    <Skeleton className="h-10" />
                </div>
                <Skeleton className="h-1.5 w-full rounded-full" />
                <Skeleton className="h-3 w-40" />
            </div>
            <div className="space-y-2 border-t border-border pt-3">
                <Skeleton className="h-4 w-full" />
                <Skeleton className="h-4 w-5/6" />
            </div>
        </article>
    );
}
