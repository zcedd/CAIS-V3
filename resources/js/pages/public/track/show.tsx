import { Button } from '@/components/ui/button';
import { show as applyShow } from '@/routes/public/apply';
import { index as trackIndex } from '@/routes/public/track';
import { Head, Link } from '@inertiajs/react';

type TrackedRequest = {
    id: number;
    program_id: number;
    program_name: string;
    status: string;
    date_requested: string | null;
    can_resume: boolean;
};

export default function PublicTrackShow({
    cais_number,
    beneficiary_name,
    last_name,
    requests,
}: {
    cais_number: string;
    beneficiary_name: string;
    last_name: string;
    requests: TrackedRequest[];
}) {
    return (
        <>
            <Head title="Request status" />
            <div className="space-y-6">
                <div className="space-y-1">
                    <h1 className="text-2xl font-semibold tracking-tight">
                        Request status
                    </h1>
                    <p className="text-sm text-muted-foreground">
                        {beneficiary_name} · {cais_number}
                    </p>
                </div>

                {requests.length === 0 ? (
                    <p className="rounded-xl border border-border bg-card p-6 text-sm text-muted-foreground">
                        No public requests were found for this CAIS number.
                    </p>
                ) : (
                    <ul className="space-y-3">
                        {requests.map((request) => (
                            <li
                                key={request.id}
                                className="rounded-xl border border-border bg-card p-4"
                            >
                                <p className="font-medium">
                                    {request.program_name}
                                </p>
                                <p className="text-sm text-muted-foreground">
                                    {request.status}
                                    {request.date_requested
                                        ? ` · Requested ${request.date_requested}`
                                        : ''}
                                </p>
                                {request.can_resume ? (
                                    <Button asChild className="mt-3" size="sm">
                                        <Link
                                            href={applyShow.url(
                                                request.program_id,
                                                {
                                                    query: {
                                                        cais_number,
                                                        last_name,
                                                    },
                                                },
                                            )}
                                        >
                                            Continue application
                                        </Link>
                                    </Button>
                                ) : null}
                            </li>
                        ))}
                    </ul>
                )}

                <Button variant="outline" asChild>
                    <Link href={trackIndex.url()}>Look up another request</Link>
                </Button>
            </div>
        </>
    );
}
