export function DashboardFiltersSkeleton() {
    return (
        <div
            className="flex flex-wrap items-center gap-2"
            aria-busy="true"
            aria-label="Loading filters"
        >
            {Array.from({ length: 7 }).map((_, index) => (
                <div
                    key={index}
                    className="h-8 w-24 animate-pulse rounded-md bg-muted"
                />
            ))}
        </div>
    );
}
