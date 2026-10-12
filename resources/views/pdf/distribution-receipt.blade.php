<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Acknowledgment receipt {{ $receipt['receipt_number'] ?? '' }}</title>
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 12px; color: #111; }
        h1 { font-size: 18px; letter-spacing: 0.08em; margin: 0 0 4px; }
        h2 { font-size: 12px; margin: 0 0 16px; font-weight: normal; }
        table { width: 100%; border-collapse: collapse; margin-top: 12px; }
        th, td { border-bottom: 1px solid #ccc; text-align: left; padding: 6px 4px; vertical-align: top; }
        .muted { color: #444; }
        .row { width: 100%; }
        .qr { width: 120px; }
    </style>
</head>
<body>
    <h1>ACKNOWLEDGMENT RECEIPT</h1>
    <h2>{{ $receipt['agency'] ?? config('app.name') }}</h2>
    <p class="muted">Status: {{ $status }}</p>
    <table>
        <tr><th>Receipt number</th><td>{{ $receipt['receipt_number'] ?? '' }}</td></tr>
        <tr><th>Transaction</th><td>{{ $receipt['transaction_number'] ?? '' }}</td></tr>
        <tr><th>Batch</th><td>{{ $receipt['batch_code'] ?? '' }}</td></tr>
        <tr><th>Program</th><td>{{ $receipt['program'] ?? '' }}</td></tr>
        <tr><th>Department</th><td>{{ $receipt['department'] ?? '' }}</td></tr>
        <tr><th>Recipient</th><td>{{ $receipt['recipient_name'] ?? '' }} ({{ $receipt['recipient_type'] ?? '' }})</td></tr>
        <tr><th>Beneficiary reference</th><td>{{ $receipt['beneficiary_reference'] ?? '' }}</td></tr>
        <tr><th>Assistance request</th><td>{{ $receipt['assistance_id'] ?? '' }}</td></tr>
        <tr><th>Date and venue</th><td>{{ $receipt['distribution_date'] ?? '' }} · {{ $receipt['venue'] ?? '' }}</td></tr>
        <tr><th>Released by</th><td>{{ $receipt['released_by'] ?? '' }}</td></tr>
        <tr><th>Witness</th><td>{{ $receipt['witness'] ?? '—' }}</td></tr>
        <tr><th>Issued</th><td>{{ $receipt['issued_at'] ?? '' }}</td></tr>
    </table>
    <table>
        <thead>
            <tr>
                <th>Assistance received</th>
                <th>Quantity</th>
                <th>Unit</th>
                <th>Amount</th>
            </tr>
        </thead>
        <tbody>
            @foreach (($receipt['items'] ?? []) as $item)
                <tr>
                    <td>{{ $item['description'] ?? '' }}</td>
                    <td>{{ $item['quantity'] ?? '—' }}</td>
                    <td>{{ $item['unit'] ?? '—' }}</td>
                    <td>{{ $item['amount'] ?? '—' }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>
    @if (! empty($receipt['total_amount']))
        <p>Total amount: {{ $receipt['total_amount'] }}</p>
    @endif
    <p>{{ $receipt['acknowledgment'] ?? '' }}</p>
    <p>Acknowledgment: {{ $receipt['acknowledgment_mode'] ?? '' }}</p>
    @if (! empty($receipt['alternative_reason']))
        <p>Alternative acknowledgment: {{ $receipt['alternative_reason'] }}</p>
    @endif
    @if (! empty($receipt['representative_relationship']))
        <p>Representative relationship: {{ $receipt['representative_relationship'] }}</p>
    @endif
    <p class="muted">{{ $receipt['evidence_note'] ?? '' }}</p>
    <div class="qr">{!! $qrSvg !!}</div>
    <p class="muted">Scan the code to confirm this receipt. The public page does not show photographs or signatures.</p>
</body>
</html>
