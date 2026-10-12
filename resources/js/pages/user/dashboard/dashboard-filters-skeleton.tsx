export function DashboardFiltersSkeleton() {
    return (
        <div
            className="h-9 w-28 animate-pulse rounded-md bg-muted"
            aria-busy="true"
            aria-label="Loading filters"
            data-tour="dashboard-filters"
        />
    );
}
