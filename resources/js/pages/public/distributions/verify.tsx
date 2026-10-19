import { Head } from '@inertiajs/react';
import { Badge } from '@/components/ui/badge';

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
        distribution_date: string | null;
        items: PublicItem[];
        total_amount: string | null;
        issued_at: string | null;
    };
}) {
    const fields = [
        { label: 'Receipt', value: receipt.receipt_number },
        { label: 'Program', value: receipt.program },
        { label: 'Department', value: receipt.department },
        { label: 'Venue', value: receipt.venue },
        { label: 'Date', value: receipt.distribution_date ?? '—' },
        { label: 'Issued', value: receipt.issued_at ?? '—' },
    ];

    return (
        <>
            <Head title="Receipt verification" />
            <div className="flex flex-col gap-6">
                <div className="flex flex-col gap-2">
                    <h1 className="text-2xl font-semibold tracking-tight">
                        Receipt verification
                    </h1>
                    <p className="text-sm text-muted-foreground">
                        This page confirms a receipt number and the assistance
                        that was released. It does not show photographs,
                        signatures, or a full beneficiary profile.
                    </p>
                </div>
                <dl className="flex flex-col gap-4 rounded-xl border border-border bg-card p-4">
                    <div className="flex flex-col gap-1">
                        <dt className="text-sm text-muted-foreground">
                            Status
                        </dt>
                        <dd>
                            <Badge variant="secondary">
                                {receipt.status_label}
                            </Badge>
                        </dd>
                    </div>
                    {fields.map((field) => (
                        <div key={field.label} className="flex flex-col gap-1">
                            <dt className="text-sm text-muted-foreground">
                                {field.label}
                            </dt>
                            <dd className="text-sm">{field.value}</dd>
                        </div>
                    ))}
                </dl>
                <ul className="flex flex-col gap-2 text-sm">
                    {receipt.items.map((item, index) => (
                        <li key={`${item.description}-${index}`}>
                            {item.description}:{' '}
                            {item.quantity ?? item.amount ?? '—'}{' '}
                            {item.unit ?? ''}
                        </li>
                    ))}
                </ul>
                {receipt.total_amount ? (
                    <p className="text-sm">
                        Total amount: {receipt.total_amount}
                    </p>
                ) : null}
            </div>
        </>
    );
}
