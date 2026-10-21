<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="utf-8">
    <title>Acknowledgment receipt {{ $document['receipt_number'] }}</title>
    <style>
        body {
            font-family: DejaVu Sans, sans-serif;
            font-size: 12px;
            color: #111;
            margin: 0;
        }

        .center {
            text-align: center;
        }

        .kicker {
            font-size: 10px;
            letter-spacing: 0.08em;
            text-transform: uppercase;
            margin: 0;
        }

        .department {
            font-size: 14px;
            font-weight: bold;
            margin: 4px 0;
        }

        .system {
            font-size: 11px;
            color: #333;
            margin: 0 0 10px;
        }

        .badge {
            display: inline-block;
            border: 1px solid #111;
            border-radius: 12px;
            padding: 3px 10px;
            font-size: 9px;
            letter-spacing: 0.08em;
            text-transform: uppercase;
        }

        table.meta {
            width: 100%;
            border-collapse: collapse;
            margin-top: 16px;
        }

        table.meta td {
            width: 50%;
            border: 1px solid #ccc;
            padding: 8px;
            vertical-align: top;
        }

        .label {
            font-size: 9px;
            letter-spacing: 0.06em;
            text-transform: uppercase;
            color: #333;
        }

        .mono {
            font-family: DejaVu Sans Mono, monospace;
        }

        .section {
            font-size: 11px;
            font-weight: bold;
            letter-spacing: 0.04em;
            text-transform: uppercase;
            margin: 18px 0 6px;
        }

        table.identity {
            width: 100%;
            border-collapse: collapse;
        }

        table.identity td {
            vertical-align: top;
            border: none;
            padding: 0;
        }

        .qr {
            width: 112px;
            text-align: right;
        }

        .qr svg {
            width: 112px;
            height: 112px;
        }

        .muted {
            color: #333;
            font-size: 11px;
        }

        h3 {
            font-size: 12px;
            margin: 12px 0 4px;
        }

        table.items {
            width: 100%;
            border-collapse: collapse;
            margin-top: 4px;
        }

        table.items th,
        table.items td {
            border-bottom: 1px solid #ccc;
            text-align: left;
            padding: 5px 6px 5px 0;
            vertical-align: top;
        }

        table.items th {
            font-size: 11px;
            font-weight: bold;
        }

        .empty {
            font-style: italic;
            color: #333;
        }

        .total {
            text-align: right;
            font-family: DejaVu Sans Mono, monospace;
            margin: 6px 0 0;
        }

        .ack {
            font-style: italic;
            margin-top: 16px;
        }

        table.signs {
            width: 100%;
            margin-top: 28px;
            border-collapse: collapse;
        }

        table.signs td {
            width: 50%;
            border: none;
            vertical-align: bottom;
            padding: 0 16px 0 0;
        }

        .sign-label {
            font-size: 10px;
            letter-spacing: 0.08em;
            text-transform: uppercase;
            font-weight: bold;
        }

        .sign-line {
            height: 72px;
            border-bottom: 1px solid #111;
        }

        .sign-line img {
            height: 68px;
        }

        .proof {
            max-width: 320px;
            max-height: 220px;
            margin-top: 8px;
        }

        .footer {
            margin-top: 16px;
            font-size: 9px;
            color: #333;
        }
    </style>
</head>

<body>
    <header class="center">
        <p class="kicker">Republic of the Philippines</p>
        <p class="department">{{ $document['department_name'] }}</p>
        <p class="system">Centralized Assistance Information System</p>
        <span class="badge">Acknowledgment receipt</span>
    </header>

    @include('pdf.partials.receipt-facts', ['rows' => $document['facts']])

    <h2 class="section">I. Beneficiary &amp; receiver</h2>
    <table class="identity">
        <tr>
            <td>
                <div><strong>{{ $document['beneficiary_name'] }}</strong></div>
                @if ($document['beneficiary_type'])
                    <div class="muted">{{ $document['beneficiary_type'] }}</div>
                @endif
                <div class="mono">{{ $document['beneficiary_id_number'] }}</div>
                <div class="muted">{{ $document['beneficiary_address'] }}</div>
            </td>
            <td class="qr">{!! $document['qr_svg'] !!}</td>
        </tr>
    </table>

    <h2 class="section">II. Particulars of assistance released</h2>

    <h3>Items actually released</h3>
    @if ($document['released_items'] === [])
        <p class="empty">No items have been marked released yet.</p>
    @else
        <table class="items">
            <thead>
                <tr>
                    <th>Item</th>
                    <th>Amount</th>
                    <th>Type</th>
                    <th>Reason / specification</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($document['released_items'] as $item)
                    <tr>
                        <td>{{ $item['name'] }}</td>
                        <td>{{ $item['amount'] }}</td>
                        <td>{{ $item['type'] }}</td>
                        <td>{{ $item['detail'] }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif
    <p class="total">Cash total {{ $document['cash_total'] }}</p>

    <h3>Items requested</h3>
    @if ($document['requested_items'] === [])
        <p class="empty">No requested items recorded.</p>
    @else
        <table class="items">
            <thead>
                <tr>
                    <th>Item</th>
                    <th>Requested</th>
                    <th>Released</th>
                    <th>Specification</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($document['requested_items'] as $item)
                    <tr>
                        <td>{{ $item['name'] }}</td>
                        <td>{{ $item['requested'] }}</td>
                        <td>{{ $item['released'] }}</td>
                        <td>{{ $item['specification'] }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif

    @if ($document['variance'])
        <p class="muted">{{ $document['variance'] }}</p>
    @endif

    <p class="ack">{{ $document['acknowledgment'] }}</p>

    @include('pdf.partials.receipt-facts', ['rows' => $document['acknowledgment_facts']])

    <table class="signs">
        <tr>
            <td>
                <div class="sign-label">Beneficiary</div>
                <div class="sign-line">
                    @if ($document['signature_src'])
                        <img src="{{ $document['signature_src'] }}" alt="Beneficiary signature">
                    @endif
                </div>
                <div>{{ $document['recipient_name'] }}</div>
            </td>
            <td>
                <div class="sign-label">Releasing officer</div>
                <div class="sign-line"></div>
                <div>{{ $document['released_by'] }}</div>
            </td>
        </tr>
    </table>

    @if ($document['photo_src'])
        <h2 class="section">Proof of delivery</h2>
        <img class="proof" src="{{ $document['photo_src'] }}" alt="Photo proof of delivery">
    @endif

    @if ($document['profile_url'])
        <p class="footer">Assistance profile: {{ $document['profile_url'] }}</p>
    @endif
    <p class="footer">Printed {{ $document['printed_at'] }}</p>
</body>

</html>
