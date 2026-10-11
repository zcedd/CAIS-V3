import { Head } from '@inertiajs/react';

type PublicItem = {
    description: string;
    quantity: string | null;
    unit: string | null;
    amount: string | null;
};

export default function PublicReceiptVerification({
    receipt,
}: {
    receipt: {
        receipt_number: string;
        status_label: string;
        program: string;
        department: string;
        venue: string;
        disbursement_date: string | null;
        items: PublicItem[];
        total_amount: string | null;
        issued_at: string | null;
    };
}) {
    return (
        <>
            <Head title="Receipt verification" />
            <div className="flex flex-col gap-4">
                <div>
                    <h1 className="text-2xl font-semibold tracking-tight">Receipt verification</h1>
                    <p className="text-sm text-muted-foreground">
                        This page confirms a receipt number and the assistance that was released. It does not show photographs, signatures, or a full beneficiary profile.
                    </p>
                </div>
                <dl className="grid gap-2 text-sm">
                    <div><dt className="text-muted-foreground">Receipt</dt><dd>{receipt.receipt_number}</dd></div>
                    <div><dt className="text-muted-foreground">Status</dt><dd>{receipt.status_label}</dd></div>
                    <div><dt className="text-muted-foreground">Program</dt><dd>{receipt.program}</dd></div>
                    <div><dt className="text-muted-foreground">Department</dt><dd>{receipt.department}</dd></div>
                    <div><dt className="text-muted-foreground">Venue</dt><dd>{receipt.venue}</dd></div>
                    <div><dt className="text-muted-foreground">Date</dt><dd>{receipt.disbursement_date ?? '—'}</dd></div>
                    <div><dt className="text-muted-foreground">Issued</dt><dd>{receipt.issued_at ?? '—'}</dd></div>
                </dl>
                <ul className="flex flex-col gap-1 text-sm">
                    {receipt.items.map((item, index) => (
                        <li key={`${item.description}-${index}`}>
                            {item.description}: {item.quantity ?? item.amount ?? '—'} {item.unit ?? ''}
                        </li>
                    ))}
                </ul>
                {receipt.total_amount && <p className="text-sm">Total amount: {receipt.total_amount}</p>}
            </div>
        </>
    );
}
