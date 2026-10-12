'use client';

import { Head, Link } from '@inertiajs/react';
import { Printer } from 'lucide-react';
import { useEffect } from 'react';
import { Button } from '@/components/ui/button';
import { EMPTY_CELL } from '@/lib/empty-cell';
import { formatPeso } from '@/lib/format-peso';
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

type ReceiptPayload = {
    assistance_id: number;
    cais_number: string;
    beneficiary_name: string;
    beneficiary_address: string | null;
    beneficiary_id_number: string | null;
    program_name: string;
    department_name: string;
    cash_total: number;
    released_by: string | null;
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
                    html, body { background: #fff !important; color: #000 !important; }
                    .receipt-document { background: #fff !important; color: #000 !important; box-shadow: none !important; }
                }
            `}</style>
            <div className="mx-auto flex min-h-screen max-w-3xl flex-col gap-6 bg-muted/40 p-6 print:max-w-none print:bg-white print:p-0">
                <div className="receipt-actions flex flex-wrap items-center justify-between gap-3 rounded-xl border border-border bg-card px-4 py-3 print:hidden">
                    <div>
                        <p className="text-sm font-medium">
                            Official Acknowledgment Receipt
                        </p>
                        <p className="text-xs text-muted-foreground">
                            {receipt.department_name}
                        </p>
                    </div>
                    <div className="flex gap-2">
                        <Button variant="outline" asChild>
                            <Link
                                href={assistanceShow.url({
                                    department: department.slug,
                                    program: program.id,
                                    assistance: receipt.assistance_id,
                                })}
                            >
                                Back
                            </Link>
                        </Button>
                        <Button type="button" onClick={() => window.print()}>
                            <Printer className="size-4" />
                            Print
                        </Button>
                    </div>
                </div>

                <article className="receipt-document space-y-6 rounded-xl border border-border bg-card p-6 text-card-foreground print:border-0 print:bg-white print:text-black">
                <header className="space-y-2 text-center">
                    <p className="text-[11px] tracking-wide uppercase">
                        Republic of the Philippines
                    </p>
                    <p className="text-sm font-medium">
                        {receipt.department_name}
                    </p>
                    <p className="text-xs text-muted-foreground print:text-black">
                        Centralized Assistance Information System
                    </p>
                    <p className="mx-auto w-fit rounded-full border border-border px-3 py-1 font-mono text-[10px] tracking-wide uppercase">
                        Acknowledgment receipt (AR)
                    </p>
                </header>

                <dl className="grid gap-3 border border-border p-3 text-sm sm:grid-cols-2">
                    <div>
                        <dt className="text-[10px] tracking-wide text-muted-foreground uppercase print:text-black">
                            Receipt number
                        </dt>
                        <dd className="font-mono">{receipt.cais_number}</dd>
                    </div>
                    <div>
                        <dt className="text-[10px] tracking-wide text-muted-foreground uppercase print:text-black">
                            Date released
                        </dt>
                        <dd>{formatDate(receipt.date_delivered)}</dd>
                    </div>
                    <div>
                        <dt className="text-[10px] tracking-wide text-muted-foreground uppercase print:text-black">
                            Program
                        </dt>
                        <dd>{receipt.program_name}</dd>
                    </div>
                    <div>
                        <dt className="text-[10px] tracking-wide text-muted-foreground uppercase print:text-black">
                            Department
                        </dt>
                        <dd>{receipt.department_name}</dd>
                    </div>
                </dl>

                <section className="grid gap-4 sm:grid-cols-[1fr_7rem]">
                    <div className="space-y-1 text-sm">
                        <h2 className="text-xs font-semibold tracking-wide uppercase">
                            I. Beneficiary & receiver
                        </h2>
                        <p className="font-medium">{receipt.beneficiary_name}</p>
                        <p className="font-mono text-xs">
                            {receipt.beneficiary_id_number ?? receipt.cais_number}
                        </p>
                        <p className="text-xs text-muted-foreground print:text-black">
                            {receipt.beneficiary_address ?? 'No address on file'}
                        </p>
                    </div>
                    <div
                        className="size-28 [&_svg]:size-full"
                        dangerouslySetInnerHTML={{ __html: receipt.qr_svg }}
                    />
                </section>

                <section className="space-y-2">
                    <h2 className="text-xs font-semibold tracking-wide uppercase">
                        II. Particulars of assistance released
                    </h2>

                <ReleasedItemsTable items={receipt.released_items} />
                <p className="text-right font-mono text-sm tabular-nums">
                    Cash total {formatPeso(receipt.cash_total)}
                </p>
                <RequestedItemsTable items={receipt.requested_items} />

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
                </section>

                <p className="text-sm italic">
                    I acknowledge that I received the assistance listed above
                    from {receipt.department_name}.
                </p>

                <section className="grid gap-8 pt-6 sm:grid-cols-2">
                    <div className="space-y-8">
                        <p className="text-xs font-medium tracking-wide uppercase">
                            Beneficiary
                        </p>
                        <div className="border-t border-foreground/40 pt-2 text-sm print:border-black">
                            {receipt.beneficiary_name}
                        </div>
                    </div>
                    <div className="space-y-8">
                        <p className="text-xs font-medium tracking-wide uppercase">
                            Releasing officer
                        </p>
                        <div className="border-t border-foreground/40 pt-2 text-sm print:border-black">
                            {receipt.released_by ?? 'Releasing officer'}
                        </div>
                    </div>
                </section>

                <p className="text-[10px] break-all text-muted-foreground print:text-black">
                    Assistance profile: {receipt.profile_url}
                </p>
                <p className="text-[10px] text-muted-foreground print:text-black">
                    Printed {formatDate(receipt.printed_at)}
                </p>
                </article>
            </div>
        </>
    );
}
