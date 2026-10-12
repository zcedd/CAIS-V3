import { Head, Link, router } from '@inertiajs/react';
import { Button } from '@/components/ui/button';
import { pdf } from '@/routes/user/distributions/transactions/receipt';
import { reprint } from '@/routes/user/distributions/receipts';
import { venue } from '@/routes/user/distributions';

type ReceiptItem = {
    description?: string;
    quantity?: string | null;
    unit?: string | null;
    amount?: string | null;
};

type ReceiptPageProps = {
    department: { slug: string; name: string };
    batch_id: number;
    transaction_id: number;
    receipt: {
        id: number;
        number: string;
        status: string;
        status_label: string;
        version: number;
        reprint_count: number;
        verify_url: string;
        snapshot: {
            agency?: string;
            receipt_number?: string;
            transaction_number?: string;
            batch_code?: string;
            program?: string;
            department?: string;
            recipient_name?: string;
            recipient_type?: string;
            beneficiary_reference?: string;
            assistance_id?: number;
            distribution_date?: string;
            venue?: string;
            items?: ReceiptItem[];
            total_amount?: string | null;
            acknowledgment?: string;
            acknowledgment_mode?: string;
            alternative_reason?: string | null;
            released_by?: string;
            witness?: string | null;
            issued_at?: string;
            evidence_note?: string;
        };
    };
};

export default function DistributionReceiptPage({
    department,
    batch_id,
    transaction_id,
    receipt,
}: ReceiptPageProps) {
    const snapshot = receipt.snapshot;
    const args = { department: department.slug, distributionBatch: batch_id };

    return (
        <>
            <Head title={`Receipt ${receipt.number}`} />
            <main className="mx-auto flex max-w-3xl flex-col gap-4 p-6 print:p-0">
                <div className="flex flex-wrap gap-2 print:hidden">
                    <Button type="button" onClick={() => window.print()}>Print</Button>
                    <Button variant="outline" asChild>
                        <a href={pdf.url({ ...args, transaction: transaction_id })}>Download PDF</a>
                    </Button>
                    <Button
                        variant="outline"
                        onClick={() =>
                            router.post(reprint.url({ ...args, receipt: receipt.id }), {}, { preserveScroll: true })
                        }
                    >
                        Record reprint
                    </Button>
                    <Button variant="outline" asChild>
                        <Link href={venue.url(args)}>Back to venue</Link>
                    </Button>
                </div>
                <header>
                    <p className="text-xs tracking-widest">ACKNOWLEDGMENT RECEIPT</p>
                    <h1 className="text-2xl font-semibold">{snapshot.agency}</h1>
                    <p className="text-sm text-muted-foreground">
                        {receipt.number} · {receipt.status_label} · version {receipt.version}
                    </p>
                </header>
                <dl className="grid gap-2 text-sm sm:grid-cols-2">
                    <Item label="Transaction" value={snapshot.transaction_number} />
                    <Item label="Batch" value={snapshot.batch_code} />
                    <Item label="Program" value={snapshot.program} />
                    <Item label="Department" value={snapshot.department} />
                    <Item label="Recipient" value={`${snapshot.recipient_name ?? ''} (${snapshot.recipient_type ?? ''})`} />
                    <Item label="Beneficiary reference" value={snapshot.beneficiary_reference} />
                    <Item label="Assistance request" value={snapshot.assistance_id} />
                    <Item label="Date and venue" value={`${snapshot.distribution_date ?? ''} · ${snapshot.venue ?? ''}`} />
                    <Item label="Released by" value={snapshot.released_by} />
                    <Item label="Witness" value={snapshot.witness} />
                    <Item label="Issued" value={snapshot.issued_at} />
                    <Item label="Reprints" value={receipt.reprint_count} />
                </dl>
                <table className="w-full text-sm">
                    <thead>
                        <tr className="border-b text-left">
                            <th className="py-2">Assistance received</th>
                            <th>Quantity</th>
                            <th>Unit</th>
                            <th>Amount</th>
                        </tr>
                    </thead>
                    <tbody>
                        {(snapshot.items ?? []).map((item, index) => (
                            <tr key={`${item.description}-${index}`} className="border-b">
                                <td className="py-2">{item.description}</td>
                                <td>{item.quantity ?? '—'}</td>
                                <td>{item.unit ?? '—'}</td>
                                <td>{item.amount ?? '—'}</td>
                            </tr>
                        ))}
                    </tbody>
                </table>
                {snapshot.total_amount && <p>Total amount: {snapshot.total_amount}</p>}
                <p>{snapshot.acknowledgment}</p>
                <p>Acknowledgment: {snapshot.acknowledgment_mode}</p>
                {snapshot.alternative_reason && <p>Alternative acknowledgment: {snapshot.alternative_reason}</p>}
                <p className="text-sm text-muted-foreground">{snapshot.evidence_note}</p>
                <p className="text-sm break-all text-muted-foreground">Verify: {receipt.verify_url}</p>
            </main>
        </>
    );
}

function Item({ label, value }: { label: string; value: string | number | null | undefined }) {
    return (
        <div>
            <dt className="text-muted-foreground">{label}</dt>
            <dd>{value || '—'}</dd>
        </div>
    );
}
