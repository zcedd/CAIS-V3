import { Skeleton } from '@/components/ui/skeleton';
import { cn } from '@/lib/utils';

export function MunicipalityMapSkeleton({
    compact = false,
}: {
    compact?: boolean;
}) {
    return (
        <section
            className={cn(
                'flex flex-col',
                compact
                    ? 'w-full'
                    : 'h-full rounded-xl border border-border bg-card p-4',
            )}
            aria-busy="true"
            aria-label="Loading municipality map"
        >
            <Skeleton className="h-4 w-32" />
            <Skeleton className="mt-2 h-3 w-64 max-w-full" />
            <Skeleton
                className={cn(
                    'mt-3 w-full',
                    compact ? 'aspect-3/4' : 'min-h-64 flex-1',
                )}
            />
        </section>
    );
}
