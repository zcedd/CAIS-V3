import type { LucideIcon } from 'lucide-react';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { cn } from '@/lib/utils';

type DashboardStatCardProps = {
    label: string;
    value: React.ReactNode;
    description?: React.ReactNode;
    icon: LucideIcon;
    footer?: React.ReactNode;
    className?: string;
    valueClassName?: string;
};

/**
 * Compact metric card used across the dashboard (Insights-style).
 */
export function DashboardStatCard({
    label,
    value,
    description,
    icon: Icon,
    footer,
    className,
    valueClassName,
}: DashboardStatCardProps) {
    return (
        <Card size="sm" className={className}>
            <CardHeader className="flex flex-row items-start justify-between gap-2">
                <div className="min-w-0 space-y-1">
                    <CardDescription>{label}</CardDescription>
                    <CardTitle
                        className={cn(
                            'text-2xl font-semibold tabular-nums',
                            valueClassName,
                        )}
                    >
                        {value}
                    </CardTitle>
                    {description ? (
                        <p className="text-xs text-muted-foreground">
                            {description}
                        </p>
                    ) : null}
                </div>
                <Icon className="size-5 shrink-0 text-muted-foreground" />
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
    'data-tour'?: string;
};

/**
 * Section card with title, description, and trailing icon (consistent chrome).
 */
export function DashboardSectionCard({
    title,
    description,
    icon: Icon,
    children,
    className,
    contentClassName,
    'data-tour': dataTour,
}: DashboardSectionCardProps) {
    return (
        <Card className={cn('gap-4', className)} data-tour={dataTour}>
            <CardHeader className="flex flex-row items-start justify-between gap-2">
                <div className="min-w-0 space-y-1">
                    <CardTitle>{title}</CardTitle>
                    {description ? (
                        <CardDescription>{description}</CardDescription>
                    ) : null}
                </div>
                <Icon className="size-5 shrink-0 text-muted-foreground" />
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
        <Card size="sm">
            <CardHeader className="flex flex-row items-start justify-between gap-2">
                <div className="space-y-2">
                    <div className="h-3 w-24 animate-pulse rounded bg-muted" />
                    <div className="h-8 w-16 animate-pulse rounded bg-muted" />
                    <div className="h-3 w-32 animate-pulse rounded bg-muted" />
                </div>
                <div className="size-5 animate-pulse rounded bg-muted" />
            </CardHeader>
            {withFooter ? (
                <CardContent>
                    <div className="h-2 w-full animate-pulse rounded bg-muted" />
                </CardContent>
            ) : null}
        </Card>
    );
}
