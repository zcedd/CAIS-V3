import { Head } from '@inertiajs/react';
import { dashboard } from '@/routes';

export default function Dashboard({
    noDepartment = false,
}: {
    noDepartment?: boolean;
}) {
    return (
        <>
            <Head title="Dashboard" />
            <div className="flex h-full flex-1 flex-col items-center justify-center gap-4 overflow-x-auto rounded-xl p-4">
                {noDepartment ? (
                    <div className="max-w-md text-center">
                        <h1 className="text-2xl font-semibold tracking-tight">
                            No department assigned
                        </h1>
                        <p className="mt-2 text-sm text-muted-foreground">
                            Your account is not linked to a department yet. Contact
                            an administrator to access the department dashboard.
                        </p>
                    </div>
                ) : (
                    <p className="text-sm text-muted-foreground">Loading…</p>
                )}
            </div>
        </>
    );
}

Dashboard.layout = {
    breadcrumbs: [
        {
            title: 'Dashboard',
            href: dashboard(),
        },
    ],
};
