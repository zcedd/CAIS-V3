import type { LucideIcon } from 'lucide-react';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { cn } from '@/lib/utils';

export type DashboardTone = 'success' | 'warning' | 'danger' | 'info';

export const dashboardSurfaceClass =
    'rounded-xl border border-slate-200 bg-white shadow-xs ring-0 dark:border-border dark:bg-card';

export const dashboardPageClass =
    'flex h-full min-w-0 flex-1 flex-col gap-4 overflow-x-hidden rounded-xl bg-slate-50 p-4 dark:bg-background';

export const dashboardHeadingClass =
    'text-2xl font-semibold tracking-tight text-slate-900 dark:text-foreground';

export const dashboardSubheadingClass =
    'text-sm text-slate-500 dark:text-muted-foreground';

export const dashboardTitleClass =
    'font-semibold tracking-tight text-slate-900 dark:text-foreground';

export const dashboardMutedClass = 'text-slate-500 dark:text-muted-foreground';

export const dashboardTabListClass =
    'h-auto w-full min-w-max justify-start gap-1 border-b border-slate-200 bg-transparent p-0 dark:border-border';

export const dashboardTabTriggerClass =
    'h-10 flex-none rounded-none px-3 text-sm font-medium text-slate-500 after:bg-slate-900 hover:text-slate-900 data-active:font-semibold data-active:text-slate-900 dark:text-muted-foreground dark:after:bg-foreground dark:hover:text-foreground dark:data-active:text-foreground';

export const dashboardProgressClass =
    'h-2 bg-slate-200 dark:bg-muted [&_[data-slot=progress-indicator]]:bg-emerald-500';

export const dashboardActivePillClass =
    'h-auto rounded-full border border-emerald-200 bg-emerald-50 px-2.5 py-0.5 text-xs font-semibold text-emerald-700 shadow-none';

export const dashboardWarningPillClass =
    'h-auto rounded-full border border-amber-200 bg-amber-50 px-2.5 py-0.5 text-xs font-semibold text-amber-700 shadow-none';

export const dashboardInfoPillClass =
    'h-auto rounded-full border border-slate-200 bg-slate-50 px-2.5 py-0.5 text-xs font-semibold text-slate-700 shadow-none';

export const dashboardFilterInputClass =
    'h-10 w-full justify-between gap-2 rounded-lg border-slate-200 bg-white px-3 font-normal shadow-none hover:bg-slate-50 dark:border-input dark:bg-background dark:hover:bg-muted';

export const dashboardFilterInputActiveClass =
    'border-emerald-200 bg-emerald-50/70 text-slate-900 hover:bg-emerald-50 dark:border-emerald-800 dark:bg-emerald-950/30 dark:text-foreground';

const toneWellClass: Record<DashboardTone, string> = {
    success: 'border-emerald-200 bg-emerald-50 text-emerald-700',
    warning: 'border-amber-200 bg-amber-50 text-amber-700',
    danger: 'border-rose-200 bg-rose-50 text-rose-700',
    info: 'border-slate-200 bg-slate-50 text-slate-600',
};

type DashboardStatCardProps = {
    label: string;
    value: React.ReactNode;
    description?: React.ReactNode;
    icon: LucideIcon;
    footer?: React.ReactNode;
    className?: string;
    valueClassName?: string;
    tone?: DashboardTone;
};

export function DashboardIconWell({
    icon: Icon,
    tone = 'info',
    className,
}: {
    icon: LucideIcon;
    tone?: DashboardTone;
    className?: string;
}) {
    return (
        <div
            className={cn(
                'flex size-9 shrink-0 items-center justify-center rounded-lg border',
                toneWellClass[tone],
                className,
            )}
        >
            <Icon className="size-4" />
        </div>
    );
}

/**
 * Compact metric card: label, bold figure, helper line, and a tinted icon well.
 */
export function DashboardStatCard({
    label,
    value,
    description,
    icon,
    footer,
    className,
    valueClassName,
    tone = 'info',
}: DashboardStatCardProps) {
    return (
        <Card
            className={cn(
                dashboardSurfaceClass,
                'gap-3 [--card-spacing:--spacing(4)]',
                className,
            )}
        >
            <CardHeader className="flex flex-row items-start justify-between gap-3">
                <div className="min-w-0 space-y-1">
                    <CardDescription
                        className={cn(
                            'text-xs font-medium tracking-wide',
                            dashboardMutedClass,
                        )}
                    >
                        {label}
                    </CardDescription>
                    <CardTitle
                        className={cn(
                            'text-2xl font-bold tracking-tight text-slate-900 tabular-nums group-data-[size=sm]/card:text-2xl dark:text-foreground',
                            valueClassName,
                        )}
                    >
                        {value}
                    </CardTitle>
                    {description ? (
                        <p className={cn('text-xs', dashboardMutedClass)}>
                            {description}
                        </p>
                    ) : null}
                </div>
                <DashboardIconWell icon={icon} tone={tone} />
            </CardHeader>
            {footer ? <CardContent>{footer}</CardContent> : null}
        </Card>
    );
}

type DashboardSectionCardProps = {
    title: string;
    description?: string;
    icon: LucideIcon;
    children: React.ReactNode;
    className?: string;
    contentClassName?: string;
    tone?: DashboardTone;
    'data-tour'?: string;
};

/**
 * Section card with title, description, and a tinted trailing icon.
 */
export function DashboardSectionCard({
    title,
    description,
    icon,
    children,
    className,
    contentClassName,
    tone = 'info',
    'data-tour': dataTour,
}: DashboardSectionCardProps) {
    return (
        <Card
            className={cn(dashboardSurfaceClass, 'gap-4', className)}
            data-tour={dataTour}
        >
            <CardHeader className="flex flex-row items-start justify-between gap-2">
                <div className="min-w-0 space-y-1">
                    <CardTitle className={dashboardTitleClass}>
                        {title}
                    </CardTitle>
                    {description ? (
                        <CardDescription className={dashboardMutedClass}>
                            {description}
                        </CardDescription>
                    ) : null}
                </div>
                <DashboardIconWell icon={icon} tone={tone} />
            </CardHeader>
            <CardContent className={contentClassName}>{children}</CardContent>
        </Card>
    );
}

export function DashboardStatCardSkeleton({
    withFooter = false,
}: {
    withFooter?: boolean;
}) {
    return (
        <Card
            className={cn(
                dashboardSurfaceClass,
                'gap-3 [--card-spacing:--spacing(4)]',
            )}
        >
            <CardHeader className="flex flex-row items-start justify-between gap-3">
                <div className="space-y-2">
                    <div className="h-3 w-24 animate-pulse rounded bg-slate-100 dark:bg-muted" />
                    <div className="h-8 w-16 animate-pulse rounded bg-slate-100 dark:bg-muted" />
                    <div className="h-3 w-32 animate-pulse rounded bg-slate-100 dark:bg-muted" />
                </div>
                <div className="size-9 animate-pulse rounded-lg border border-slate-200 bg-slate-50 dark:border-border dark:bg-muted" />
            </CardHeader>
            {withFooter ? (
                <CardContent>
                    <div className="h-2 w-full animate-pulse rounded-full bg-slate-200 dark:bg-muted" />
                </CardContent>
            ) : null}
        </Card>
    );
}
