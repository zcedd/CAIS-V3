import type { LucideIcon } from 'lucide-react';
import type { ReactNode } from 'react';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { Skeleton } from '@/components/ui/skeleton';
import { cn } from '@/lib/utils';

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
        <Card size="sm" className={cn('gap-4', className)} data-tour={dataTour}>
            <CardHeader className="flex flex-row items-start justify-between gap-2">
                <div className="min-w-0 space-y-1">
                    <CardTitle className="text-sm">{title}</CardTitle>
                    {description ? (
                        <CardDescription className="text-xs">
                            {description}
                        </CardDescription>
                    ) : null}
                </div>
                <Icon className="size-4 shrink-0 text-muted-foreground" />
            </CardHeader>
            <CardContent className={contentClassName}>{children}</CardContent>
        </Card>
    );
}

export function SectionCardSkeleton({
    label,
    titleClassName = 'w-32',
    descriptionClassName = 'w-48',
    className,
    children,
}: {
    label: string;
    titleClassName?: string;
    descriptionClassName?: string;
    className?: string;
    children: ReactNode;
}) {
    return (
        <Card
            aria-busy="true"
            aria-label={label}
            size="sm"
            className={cn('h-full gap-4', className)}
        >
            <CardHeader className="flex flex-row items-start justify-between gap-2">
                <div className="min-w-0 space-y-1">
                    <Skeleton className={cn('h-4', titleClassName)} />
                    <Skeleton className={cn('h-3', descriptionClassName)} />
                </div>
                <Skeleton className="size-4 shrink-0" />
            </CardHeader>
            <CardContent>{children}</CardContent>
        </Card>
    );
}
