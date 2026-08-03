export function DashboardFiltersSkeleton() {
    return (
        <div
            className="overflow-hidden rounded-2xl bg-card ring-1 ring-foreground/10"
            id="filter-bar"
            aria-busy="true"
            aria-label="Loading filters"
            data-tour="dashboard-filters"
        >
            <div className="flex flex-wrap items-center justify-between gap-3 border-b border-border/60 px-4 py-3">
                <div className="flex min-w-0 flex-1 items-center gap-2">
                    <div className="size-8 shrink-0 animate-pulse rounded-lg bg-muted" />
                    <div className="min-w-0 flex-1 space-y-1.5">
                        <div className="flex items-center gap-2">
                            <div className="h-4 w-14 animate-pulse rounded bg-muted" />
                        </div>
                        <div className="h-3 w-48 max-w-full animate-pulse rounded bg-muted" />
                    </div>
                    <div className="size-4 shrink-0 animate-pulse rounded bg-muted" />
                </div>
            </div>

            <div className="space-y-4 p-4">
                <div className="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
                    {Array.from({ length: 4 }).map((_, index) => (
                        <div key={index} className="min-w-0 space-y-1.5">
                            <div className="h-3 w-16 animate-pulse rounded bg-muted" />
                            <div className="h-10 w-full animate-pulse rounded-lg border border-transparent bg-muted" />
                        </div>
                    ))}
                </div>

                <div className="-ml-2 flex h-8 w-44 items-center gap-2 px-2">
                    <div className="size-4 animate-pulse rounded bg-muted" />
                    <div className="h-3.5 w-32 animate-pulse rounded bg-muted" />
                    <div className="size-4 animate-pulse rounded bg-muted" />
                </div>
            </div>
        </div>
    );
}
