import { Head, Link, router } from '@inertiajs/react';
import { Copy } from 'lucide-react';
import { useEffect, useState } from 'react';
import { Button } from '@/components/ui/button';
import { index as applyIndex } from '@/routes/public/apply';
import { index as trackIndex } from '@/routes/public/track';

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
    const [copied, setCopied] = useState(false);
    const saved = intent === 'save';

    useEffect(() => {
        if (!kiosk) {
            return;
        }

        const timeout = window.setTimeout(() => {
            router.visit(applyIndex.url({ query: { kiosk: 1 } }));
        }, 15000);

        return () => window.clearTimeout(timeout);
    }, [kiosk]);

    async function copyNumber(): Promise<void> {
        try {
            await navigator.clipboard.writeText(cais_number);
            setCopied(true);
        } catch {
            setCopied(false);
        }
    }

    return (
        <>
            <Head title="Request received" />
            <div className="flex flex-col gap-6">
                <div className="flex flex-col gap-2">
                    <h1 className="text-2xl font-semibold tracking-tight">
                        {saved ? 'Saved for later' : 'Request submitted'}
                    </h1>
                    <p className="text-sm text-muted-foreground">
                        {saved
                            ? `Your draft for ${program_name} was saved. Use your CAIS number and last name to continue later.`
                            : `Your request for ${program_name} was submitted. Staff will review it inside CAIS.`}
                    </p>
                </div>
                <div className="flex flex-col gap-3 rounded-xl border border-border bg-card p-5">
                    <p className="text-sm text-muted-foreground">
                        Your CAIS number
                    </p>
                    <p className="text-3xl font-semibold tracking-tight">
                        {cais_number}
                    </p>
                    <Button
                        type="button"
                        variant="outline"
                        className="w-fit"
                        onClick={() => void copyNumber()}
                    >
                        <Copy data-icon="inline-start" />
                        {copied ? 'Copied' : 'Copy number'}
                    </Button>
                </div>
                <div className="flex flex-wrap gap-2">
                    <Button asChild>
                        <Link
                            href={trackIndex.url({
                                query: { cais_number },
                            })}
                        >
                            Track this request
                        </Link>
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
