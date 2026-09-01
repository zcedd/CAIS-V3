'use client';

import { Button } from '@/components/ui/button';
import { show as assistanceShow } from '@/routes/user/assistances';
import { Head, Link } from '@inertiajs/react';
import { Printer } from 'lucide-react';
import { useEffect } from 'react';

type ReceiptItem = {
    name: string;
    quantity: number | null;
    unit: string | null;
    specification: string | null;
    is_received: boolean;
};

type ReceiptPayload = {
    assistance_id: number;
    cais_number: string;
    beneficiary_name: string;
    date_requested: string | null;
    date_delivered: string | null;
    printed_at: string;
    profile_url: string;
    qr_svg: string;
    requested_items: ReceiptItem[];
    released_items: ReceiptItem[];
};

function formatDate(value: string | null | undefined): string {
    if (!value) {
        return '—';
    }

    const parsed = new Date(value);

    if (Number.isNaN(parsed.getTime())) {
        return '—';
    }

    return parsed.toLocaleDateString(undefined, { dateStyle: 'medium' });
}

function formatAmount(item: ReceiptItem): string {
    if (item.quantity !== null && item.unit) {
        return `${item.quantity} ${item.unit}`;
    }

    if (item.quantity !== null) {
        return String(item.quantity);
    }

    return item.unit ?? '—';
}

function ItemsTable({
    title,
    items,
    emptyLabel,
}: {
    title: string;
    items: ReceiptItem[];
    emptyLabel: string;
}) {
    return (
        <section className="space-y-2">
            <h2 className="text-sm font-semibold tracking-tight">{title}</h2>
            {items.length === 0 ? (
                <p className="text-sm text-muted-foreground italic">
                    {emptyLabel}
                </p>
            ) : (
                <table className="w-full border-collapse text-sm">
                    <thead>
                        <tr className="border-b border-border text-left">
                            <th className="py-1.5 pr-3 font-medium">Item</th>
                            <th className="py-1.5 pr-3 font-medium">Amount</th>
                            <th className="py-1.5 font-medium">
                                Specification
                            </th>
                        </tr>
                    </thead>
                    <tbody>
                        {items.map((item, index) => (
                            <tr
                                key={`${item.name}-${index}`}
                                className="border-b border-border/70"
                            >
                                <td className="py-1.5 pr-3">{item.name}</td>
                                <td className="py-1.5 pr-3 tabular-nums">
                                    {formatAmount(item)}
                                </td>
                                <td className="py-1.5 text-muted-foreground">
                                    {item.specification?.trim()
                                        ? item.specification
                                        : '—'}
                                </td>
                            </tr>
                        ))}
                    </tbody>
                </table>
            )}
        </section>
    );
}

export default function UserAssistanceReceipt({
    department,
    program,
    receipt,
}: {
    department: { id: number; name: string; slug: string };
    program: { id: number; name: string };
    receipt: ReceiptPayload;
}) {
    const heading =
        receipt.cais_number !== '—'
            ? `Acknowledgment · ${receipt.cais_number}`
            : `Acknowledgment · Assistance #${receipt.assistance_id}`;

    useEffect(() => {
        const previousTitle = document.title;
        document.title = heading;

        return () => {
            document.title = previousTitle;
        };
    }, [heading]);

    return (
        <>
            <Head title={heading} />
            <style>{`
                @media print {
                    .receipt-actions { display: none !important; }
                    body { background: #fff !important; }
                }
            `}</style>
            <div className="mx-auto flex min-h-screen max-w-3xl flex-col gap-6 bg-background p-6 text-foreground print:max-w-none print:p-0">
                <div className="receipt-actions flex flex-wrap items-center justify-between gap-3 print:hidden">
                    <Button variant="outline" asChild>
                        <Link
                            href={assistanceShow.url({
                                department: department.slug,
                                program: program.id,
                                assistance: receipt.assistance_id,
                            })}
                        >
                            Back to assistance
                        </Link>
                    </Button>
                    <Button type="button" onClick={() => window.print()}>
                        <Printer className="size-4" />
                        Print
                    </Button>
                </div>

                <header className="flex flex-wrap items-start justify-between gap-6 border-b border-border pb-4">
                    <div className="space-y-1">
                        <p className="text-xs font-medium tracking-wide text-muted-foreground uppercase">
                            Centralized Assistance Information System
                        </p>
                        <h1 className="text-2xl font-semibold tracking-tight">
                            Acknowledgment receipt
                        </h1>
                        <p className="text-sm text-muted-foreground">
                            {department.name} · {program.name}
                        </p>
                    </div>
                    <div className="flex flex-col items-center gap-1">
                        <div
                            className="size-28 [&_svg]:size-full"
                            dangerouslySetInnerHTML={{ __html: receipt.qr_svg }}
                        />
                        <p className="text-[10px] text-muted-foreground">
                            Scan to open assistance profile
                        </p>
                    </div>
                </header>

                <dl className="grid gap-4 sm:grid-cols-2">
                    <div>
                        <dt className="text-xs font-medium tracking-wide text-muted-foreground uppercase">
                            CAIS number
                        </dt>
                        <dd className="mt-1 font-mono text-sm font-medium">
                            {receipt.cais_number}
                        </dd>
                    </div>
                    <div>
                        <dt className="text-xs font-medium tracking-wide text-muted-foreground uppercase">
                            Beneficiary
                        </dt>
                        <dd className="mt-1 text-sm font-medium">
                            {receipt.beneficiary_name}
                        </dd>
                    </div>
                    <div>
                        <dt className="text-xs font-medium tracking-wide text-muted-foreground uppercase">
                            Date requested
                        </dt>
                        <dd className="mt-1 text-sm tabular-nums">
                            {formatDate(receipt.date_requested)}
                        </dd>
                    </div>
                    <div>
                        <dt className="text-xs font-medium tracking-wide text-muted-foreground uppercase">
                            Date delivered
                        </dt>
                        <dd className="mt-1 text-sm tabular-nums">
                            {formatDate(receipt.date_delivered)}
                        </dd>
                    </div>
                </dl>

                <ItemsTable
                    title="Items requested"
                    items={receipt.requested_items}
                    emptyLabel="No requested items recorded."
                />

                <ItemsTable
                    title="Items actually released"
                    items={receipt.released_items}
                    emptyLabel="No items have been marked released yet."
                />

                <section className="grid gap-8 pt-8 sm:grid-cols-2">
                    <div className="space-y-10">
                        <p className="text-sm font-medium">Received by</p>
                        <div className="border-t border-foreground/40 pt-2 text-xs text-muted-foreground">
                            Signature over printed name
                        </div>
                        <div className="border-t border-foreground/40 pt-2 text-xs text-muted-foreground">
                            Date
                        </div>
                    </div>
                    <div className="space-y-10">
                        <p className="text-sm font-medium">Released by</p>
                        <div className="border-t border-foreground/40 pt-2 text-xs text-muted-foreground">
                            Signature over printed name
                        </div>
                        <div className="border-t border-foreground/40 pt-2 text-xs text-muted-foreground">
                            Date
                        </div>
                    </div>
                </section>

                <p className="text-[10px] break-all text-muted-foreground">
                    Assistance profile: {receipt.profile_url}
                </p>
                <p className="text-[10px] text-muted-foreground">
                    Printed {formatDate(receipt.printed_at)}
                </p>
            </div>
        </>
    );
}
