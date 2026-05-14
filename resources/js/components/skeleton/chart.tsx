export function Chart() {
    return (
        <div role="status" className="h-full w-full animate-pulse rounded-sm border border-gray-200 p-4 shadow-sm md:p-6 dark:border-gray-700">
            <div className="mb-2.5 h-1 w-32 rounded-full bg-gray-200 dark:bg-gray-700"></div>
            <div className="mb-10 h-1.5 w-48 rounded-full bg-gray-200 dark:bg-gray-700"></div>
            <div className="mt-4 flex items-baseline">
                <div className="h-12 w-full rounded-t-lg bg-gray-200 dark:bg-gray-700"></div>
                <div className="ms-6 h-32 w-full rounded-t-lg bg-gray-200 dark:bg-gray-700"></div>
                <div className="ms-6 h-34 w-full rounded-t-lg bg-gray-200 dark:bg-gray-700"></div>
                <div className="ms-6 h-45 w-full rounded-t-lg bg-gray-200 dark:bg-gray-700"></div>
                <div className="ms-6 h-32 w-full rounded-t-lg bg-gray-200 dark:bg-gray-700"></div>
                <div className="ms-6 h-34 w-full rounded-t-lg bg-gray-200 dark:bg-gray-700"></div>
                <div className="ms-6 h-31 w-full rounded-t-lg bg-gray-200 dark:bg-gray-700"></div>
            </div>
            <span className="sr-only">Loading...</span>
        </div>
    );
}
