'use client';

import { Button } from '@/components/ui/button';
import { show as assistanceShow } from '@/routes/user/assistances';
import {
    ASSISTANCE_ITEM_ORIGIN_LABELS,
    formatItemQuantity,
} from '@/types/assistance-item';
import type {
    AssistanceItemVariance,
    AssistanceReleasedItem,
    AssistanceRequestedItem,
} from '@/types/assistance-item';
import { Head, Link } from '@inertiajs/react';
import { EMPTY_CELL } from '@/lib/empty-cell';
import { Printer } from 'lucide-react';
import { useEffect } from 'react';

type ReceiptPayload = {
    assistance_id: number;
    cais_number: string;
    beneficiary_name: string;
    date_requested: string | null;
    date_delivered: string | null;
    printed_at: string;
    profile_url: string;
    qr_svg: string;
    requested_items: AssistanceRequestedItem[];
    released_items: AssistanceReleasedItem[];
    item_variance: AssistanceItemVariance;
};

function formatDate(value: string | null | undefined): string {
    if (!value) {
        return EMPTY_CELL;
    }

    const parsed = new Date(value);

    if (Number.isNaN(parsed.getTime())) {
        return EMPTY_CELL;
    }

    return parsed.toLocaleDateString(undefined, { dateStyle: 'medium' });
}

function RequestedItemsTable({ items }: { items: AssistanceRequestedItem[] }) {
    return (
        <section className="space-y-2">
            <h2 className="text-sm font-semibold tracking-tight">
                Items requested
            </h2>
            {items.length === 0 ? (
                <p className="text-sm text-muted-foreground italic">
                    No requested items recorded.
                </p>
            ) : (
                <table className="w-full border-collapse text-sm">
                    <thead>
                        <tr className="border-b border-border text-left">
                            <th className="py-1.5 pr-3 font-medium">Item</th>
                            <th className="py-1.5 pr-3 font-medium">
                                Requested
                            </th>
                            <th className="py-1.5 pr-3 font-medium">
                                Released
                            </th>
                            <th className="py-1.5 font-medium">
                                Specification
                            </th>
                        </tr>
                    </thead>
                    <tbody>
                        {items.map((item) => (
                            <tr
                                key={item.item_id}
                                className="border-b border-border/70"
                            >
                                <td className="py-1.5 pr-3">{item.name}</td>
                                <td className="py-1.5 pr-3 tabular-nums">
                                    {formatItemQuantity(
                                        item.requested_quantity,
                                        item.unit,
                                        item.kind,
                                    )}
                                </td>
                                <td className="py-1.5 pr-3 tabular-nums">
                                    {formatItemQuantity(
                                        item.released_quantity,
                                        item.unit,
                                        item.kind,
                                    )}
                                    {item.substituted_quantity > 0
                                        ? ' (substituted)'
                                        : ''}
                                </td>
                                <td className="py-1.5 text-muted-foreground">
                                    {item.specification?.trim()
                                        ? item.specification
                                        : EMPTY_CELL}
                                </td>
                            </tr>
                        ))}
                    </tbody>
                </table>
            )}
        </section>
    );
}

function ReleasedItemsTable({ items }: { items: AssistanceReleasedItem[] }) {
    return (
        <section className="space-y-2">
            <h2 className="text-sm font-semibold tracking-tight">
                Items actually released
            </h2>
            {items.length === 0 ? (
                <p className="text-sm text-muted-foreground italic">
                    No items have been marked released yet.
                </p>
            ) : (
                <table className="w-full border-collapse text-sm">
                    <thead>
                        <tr className="border-b border-border text-left">
                            <th className="py-1.5 pr-3 font-medium">Item</th>
                            <th className="py-1.5 pr-3 font-medium">Amount</th>
                            <th className="py-1.5 pr-3 font-medium">Type</th>
                            <th className="py-1.5 font-medium">
                                Reason / specification
                            </th>
                        </tr>
                    </thead>
                    <tbody>
                        {items.map((item) => (
                            <tr
                                key={item.id}
                                className="border-b border-border/70"
                            >
                                <td className="py-1.5 pr-3">{item.name}</td>
                                <td className="py-1.5 pr-3 tabular-nums">
                                    {formatItemQuantity(
                                        item.quantity,
                                        item.unit,
                                        item.kind,
                                    )}
                                </td>
                                <td className="py-1.5 pr-3">
                                    {ASSISTANCE_ITEM_ORIGIN_LABELS[item.origin]}
                                    {item.substituted_for_name
                                        ? ` for ${item.substituted_for_name}`
                                        : ''}
                                </td>
                                <td className="py-1.5 text-muted-foreground">
                                    {[
                                        item.fulfillment_reason,
                                        item.specification,
                                    ]
                                        .map((value) => value?.trim())
                                        .filter(Boolean)
                                        .join(' · ') || EMPTY_CELL}
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
        receipt.cais_number !== EMPTY_CELL
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

                <RequestedItemsTable items={receipt.requested_items} />

                <ReleasedItemsTable items={receipt.released_items} />

                {receipt.item_variance.has_variance ? (
                    <p className="text-xs text-muted-foreground">
                        Variance against the request:{' '}
                        {[
                            receipt.item_variance.additional_quantity > 0
                                ? `${receipt.item_variance.additional_quantity} additional`
                                : null,
                            receipt.item_variance.substitute_quantity > 0
                                ? `${receipt.item_variance.substitute_quantity} substitute`
                                : null,
                            receipt.item_variance.shortfall_quantity > 0
                                ? `${receipt.item_variance.shortfall_quantity} not yet released`
                                : null,
                        ]
                            .filter(Boolean)
                            .join(', ')}
                        .
                    </p>
                ) : null}

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
