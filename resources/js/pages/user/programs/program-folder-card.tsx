import { Skeleton } from '@/components/ui/skeleton';
import { cn } from '@/lib/utils';

export type ProgramListRow = {
    id: number;
    name: string;
    descriptions: string | null;
    start_at: string | null;
    end_at: string | null;
    is_closed: boolean | null;
    is_organization: boolean | null;
};

type ProgramFolderCardProps = {
    program: ProgramListRow;
    className?: string;
};

function formatPeriod(startAt: string | null, endAt: string | null): string {
    if (!startAt && !endAt) {
        return 'No schedule';
    }

    if (startAt && endAt) {
        return `${startAt} – ${endAt}`;
    }

    return startAt ?? endAt ?? 'No schedule';
}

export function ProgramFolderCard({
    program,
    className,
}: ProgramFolderCardProps) {
    const isClosed = Boolean(program.is_closed);
    const description = program.descriptions?.trim();

    return (
        <article
            className={cn(
                'group flex h-full min-h-42 flex-col',
                'rounded-xl border border-border bg-card',
                'transition-colors duration-150',
                'hover:border-foreground/25 hover:bg-muted/30',
                className,
            )}
        >
            <div className="flex flex-1 flex-col gap-3 p-4">
                <div className="flex items-start justify-between gap-3">
                    <div className="min-w-0 space-y-1.5">
                        <h3 className="line-clamp-2 text-[15px] leading-snug font-semibold tracking-tight">
                            {program.name}
                        </h3>
                        <p className="text-xs text-muted-foreground">
                            <span>
                                {program.is_organization
                                    ? 'Organization'
                                    : 'Individual'}
                            </span>
                            <span className="mx-1.5 text-border">·</span>
                            <span
                                className={cn(
                                    isClosed
                                        ? 'text-muted-foreground'
                                        : 'text-foreground/80',
                                )}
                            >
                                {isClosed ? 'Closed' : 'Open'}
                            </span>
                        </p>
                    </div>

                    <span
                        aria-hidden
                        className={cn(
                            'mt-1.5 size-2 shrink-0 rounded-full',
                            isClosed ? 'bg-muted-foreground/40' : 'bg-emerald-600',
                        )}
                    />
                </div>

                <p
                    className={cn(
                        'line-clamp-3 flex-1 text-sm leading-relaxed',
                        description
                            ? 'text-muted-foreground'
                            : 'text-muted-foreground/60 italic',
                    )}
                >
                    {description || 'No description'}
                </p>

                <div className="mt-auto flex items-center justify-between gap-3 border-t border-border pt-3">
                    <p className="truncate text-xs tabular-nums text-muted-foreground">
                        {formatPeriod(program.start_at, program.end_at)}
                    </p>
                    <span className="shrink-0 text-xs font-medium text-muted-foreground transition-colors group-hover:text-foreground">
                        View
                    </span>
                </div>
            </div>
        </article>
    );
}

export function ProgramFolderCardSkeleton() {
    return (
        <div className="flex h-full min-h-42 flex-col rounded-xl border border-border bg-card p-4">
            <div className="flex items-start justify-between gap-3">
                <div className="flex-1 space-y-2">
                    <Skeleton className="h-4 w-3/4 rounded-sm" />
                    <Skeleton className="h-3 w-28 rounded-sm" />
                </div>
                <Skeleton className="mt-1.5 size-2 rounded-full" />
            </div>
            <div className="mt-3 space-y-2">
                <Skeleton className="h-3 w-full rounded-sm" />
                <Skeleton className="h-3 w-5/6 rounded-sm" />
            </div>
            <div className="mt-auto flex items-center justify-between border-t border-border pt-3">
                <Skeleton className="h-3 w-32 rounded-sm" />
                <Skeleton className="h-3 w-8 rounded-sm" />
            </div>
        </div>
    );
}
