import type { LucideIcon } from 'lucide-react';
import type { ReactNode } from 'react';
import { cn } from '@/lib/utils';

export function PageHero({
    icon: Icon,
    title,
    badge,
    description,
    actions,
    className,
}: {
    icon?: LucideIcon;
    title: string;
    badge?: string;
    description?: string;
    actions?: ReactNode;
    className?: string;
}) {
    return (
        <div
            className={cn(
                'flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between',
                className,
            )}
        >
            <div className="flex min-w-0 items-start gap-3">
                {Icon ? (
                    <span className="mt-0.5 flex size-9 shrink-0 items-center justify-center rounded-lg border border-border bg-muted/50 text-muted-foreground">
                        <Icon className="size-4" />
                    </span>
                ) : null}
                <div className="min-w-0">
                    <div className="flex flex-wrap items-center gap-2">
                        <h1 className="text-lg font-semibold tracking-tight">
                            {title}
                        </h1>
                        {badge ? (
                            <span className="rounded-md border border-border bg-muted/60 px-1.5 py-0.5 font-mono text-[10px] tracking-wide text-muted-foreground uppercase">
                                {badge}
                            </span>
                        ) : null}
                    </div>
                    {description ? (
                        <p className="mt-1 max-w-3xl text-xs text-muted-foreground">
                            {description}
                        </p>
                    ) : null}
                </div>
            </div>
            {actions ? (
                <div className="flex shrink-0 flex-wrap items-center gap-2">
                    {actions}
                </div>
            ) : null}
        </div>
    );
}
