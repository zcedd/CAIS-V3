import type { LucideIcon } from 'lucide-react';
import type { ReactNode } from 'react';
import { Skeleton } from '@/components/ui/skeleton';
import { cn } from '@/lib/utils';

export function StatCard({
    label,
    value,
    description,
    icon: Icon,
    footer,
    className,
    valueClassName,
}: {
    label: string;
    value: ReactNode;
    description?: ReactNode;
    icon?: LucideIcon;
    footer?: ReactNode;
    className?: string;
    valueClassName?: string;
}) {
    return (
        <div
            className={cn(
                'rounded-xl border border-border bg-card p-4',
                className,
            )}
        >
            <div className="flex items-center justify-between gap-2">
                <p className="text-xs text-muted-foreground">{label}</p>
                {Icon ? (
                    <Icon className="size-4 shrink-0 text-muted-foreground" />
                ) : null}
            </div>
            <p
                className={cn(
                    'mt-1.5 text-3xl font-semibold tracking-tight tabular-nums',
                    valueClassName,
                )}
            >
                {value}
            </p>
            {description ? (
                <div className="mt-1 text-xs text-muted-foreground">
                    {description}
                </div>
            ) : null}
            {footer ? <div className="mt-2">{footer}</div> : null}
        </div>
    );
}

export function StatCardSkeleton({
    withFooter = false,
}: {
    withFooter?: boolean;
}) {
    return (
        <div className="rounded-xl border border-border bg-card p-4">
            <div className="flex items-center justify-between gap-2">
                <Skeleton className="h-3 w-24 rounded-sm" />
                <Skeleton className="size-4 rounded-sm" />
            </div>
            <Skeleton className="mt-2 h-8 w-16 rounded-sm" />
            <Skeleton className="mt-2 h-3 w-32 rounded-sm" />
            {withFooter ? (
                <Skeleton className="mt-2 h-2 w-full rounded-sm" />
            ) : null}
        </div>
    );
}
