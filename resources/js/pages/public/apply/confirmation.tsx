import { Button } from '@/components/ui/button';
import { index as applyIndex } from '@/routes/public/apply';
import { index as trackIndex } from '@/routes/public/track';
import { Head, Link, router } from '@inertiajs/react';
import { useEffect } from 'react';

export default function PublicApplyConfirmation({
    cais_number,
    intent,
    program_name,
    kiosk = false,
}: {
    cais_number: string;
    intent: 'save' | 'submit';
    program_name: string;
    kiosk?: boolean;
}) {
    useEffect(() => {
        if (!kiosk) {
            return;
        }

        const timeout = window.setTimeout(() => {
            router.visit(applyIndex.url({ query: { kiosk: 1 } }));
        }, 15000);

        return () => window.clearTimeout(timeout);
    }, [kiosk]);

    const saved = intent === 'save';

    return (
        <>
            <Head title="Request received" />
            <div className="space-y-4">
                <h1 className="text-2xl font-semibold tracking-tight">
                    {saved ? 'Saved for later' : 'Request submitted'}
                </h1>
                <p className="text-sm text-muted-foreground">
                    {saved
                        ? `Your draft for ${program_name} was saved. Use your CAIS number and last name to continue later.`
                        : `Your request for ${program_name} was submitted. Staff will review it inside CAIS.`}
                </p>
                <div className="rounded-xl border border-border bg-card p-4">
                    <p className="text-xs font-medium tracking-wide text-muted-foreground uppercase">
                        Your CAIS number
                    </p>
                    <p className="mt-1 text-2xl font-semibold tracking-tight">
                        {cais_number}
                    </p>
                </div>
                <div className="flex flex-wrap gap-2">
                    <Button asChild>
                        <Link href={trackIndex.url()}>Track this request</Link>
                    </Button>
                    <Button variant="outline" asChild>
                        <Link href={applyIndex.url()}>Back to programs</Link>
                    </Button>
                </div>
                {kiosk ? (
                    <p className="text-sm text-muted-foreground">
                        This screen goes back to the program list in a few
                        seconds.
                    </p>
                ) : null}
            </div>
        </>
    );
}
