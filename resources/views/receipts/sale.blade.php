@php
    $fmt = fn($n) => 'Rp ' . number_format((float)$n, 0, ',', '.');
@endphp
<!doctype html>
<html>
<head>
    <meta charset="utf-8">
    <title>Receipt {{ $sale->number ?? $sale->id }}</title>
    <style>
        * { box-sizing: border-box; }
        html, body { margin:0; padding:0; }
        body {
            font-family: ui-sans-serif, system-ui, -apple-system, Segoe UI, Roboto, Arial, "Helvetica Neue", "Noto Sans", "Liberation Sans", sans-serif;
            color:#0f172a;
            padding:16px;
            background:#fff;
        }
        .wrap { max-width: 680px; margin: 0 auto; }
        .card {
            border: 1px solid #e5e7eb;
            border-radius: 14px;
            padding: 18px;
        }
        .head {
            display:flex; align-items:center; justify-content:space-between; gap:12px; margin-bottom:6px;
        }
        .brand {
            font-size: 18px; font-weight: 700; line-height:1.2;
        }
        .muted { color:#6b7280; font-size:12px; }
        .pill {
            display:inline-block; font-size:11px; padding:4px 8px; border-radius:999px; background:#eef2ff; color:#3730a3; font-weight:600;
        }
        .grid2 { display:grid; grid-template-columns: 1fr 1fr; gap:10px; margin-top:10px; }
        .kv { display:flex; justify-content:space-between; gap:8px; margin:2px 0; }
        .kv .k { color:#475569; }
        .kv .v { font-weight:600; }
        table { width:100%; border-collapse:collapse; margin-top:14px; }
        thead th {
            text-align:left; font-size:12px; color:#475569; font-weight:600;
            padding:8px 6px; border-bottom:1px solid #e5e7eb; background:#f8fafc;
        }
        tbody td {
            font-size:12px; padding:8px 6px; border-bottom:1px solid #f1f5f9;
        }
        tbody tr:nth-child(even) td { background:#fafafa; }
        th:nth-child(2), td:nth-child(2) { text-align:center; width:64px; }
        th:nth-child(3), td:nth-child(3) { text-align:right; width:96px; }
        th:nth-child(4), td:nth-child(4) { text-align:right; width:110px; }
        .totals { margin-top:10px; }
        .totals .row { display:flex; justify-content:flex-end; gap:12px; margin:4px 0; }
        .totals .label { min-width:140px; text-align:right; color:#475569; }
        .totals .value { min-width:110px; text-align:right; font-weight:600; }
        .totals .grand .label, .totals .grand .value { font-weight:800; }
        .footer { margin-top:12px; font-size:12px; color:#64748b; text-align:center; }
        .btn {
            display:inline-block; background:#111827; color:#fff; border-radius:10px; padding:8px 12px; text-decoration:none;
        }
        @media print {
            .no-print { display:none !important; }
            .card { border:0; padding:0; }
            body { padding:0; }
        }
    </style>
</head>
<body>
<div class="wrap">

    @if (!($asPdf ?? false))
        <div class="no-print" style="margin-bottom:12px;">
            <a class="btn" href="{{ route('receipt.pdf', ['sale' => $sale->getKey()]) }}">Download PDF</a>
        </div>
    @endif

    <div class="card">
        <div class="head">
            <div>
                <div class="brand">{{ config('app.name', 'Store') }}</div>
                {{-- <div class="muted">{{ url('/') }}</div> --}}
            </div>
            <div style="text-align:right">
                <div><span class="pill">{{ strtoupper($sale->payment_method ?? '-') }}</span></div>
                <div class="muted" style="margin-top:4px;">Status: <strong>{{ ucfirst($sale->status) }}</strong></div>
            </div>
        </div>

        <div class="grid2">
            <div>
                <div class="kv"><div class="k">No. Nota</div><div class="v">{{ $sale->number ?? $sale->id }}</div></div>
                <div class="kv"><div class="k">Tanggal</div><div class="v">{{ $sale->created_at?->format('d M Y H:i') }}</div></div>
            </div>
            <div>
                <div class="kv"><div class="k">Pelanggan</div><div class="v">{{ $sale->customer_name ?: '-' }}</div></div>
            </div>
        </div>

        <table>
            <thead>
                <tr>
                    <th>Item</th>
                    <th>Qty</th>
                    <th>Harga</th>
                    <th>Subtotal</th>
                </tr>
            </thead>
            <tbody>
            @foreach($sale->items as $item)
                @php
                    // Pastikan relasi dimuat: items.productVariant.product
                    $pv   = $item->relationLoaded('productVariant') ? $item->productVariant : ($item->productVariant ?? null);
                    $prod = $pv?->relationLoaded('product') ? $pv->product : ($pv->product ?? null);

                    // Rangkai nama tampilan yang bagus: "Produk / Varian"
                    $base   = trim((string)($prod->title ?? ''));
                    $var    = trim((string)($pv->title ?? ''));
                    $guessed = $base && $var ? ($base.' / '.$var) : ($base ?: ($var ?: null));

                    $display = $item->name ?: ($guessed ?: ($item->sku ?: 'Item'));
                @endphp
                <tr>
                    <td>{{ $display }}</td>
                    <td>{{ $item->qty }}</td>
                    <td>{{ $fmt($item->price) }}</td>
                    <td>{{ $fmt($item->line_total) }}</td>
                </tr>
            @endforeach
            </tbody>
        </table>

        <div class="totals">
            <div class="row"><div class="label">Subtotal</div><div class="value">{{ $fmt($sale->subtotal) }}</div></div>
            <div class="row"><div class="label">Diskon</div><div class="value">{{ $fmt($sale->discount) }}</div></div>
            <div class="row"><div class="label">Pajak</div><div class="value">{{ $fmt($sale->tax) }}</div></div>
            <div class="row grand"><div class="label">Total</div><div class="value">{{ $fmt($sale->total) }}</div></div>
            <div class="row"><div class="label">Dibayar</div><div class="value">{{ $fmt($sale->paid_amount) }}</div></div>
            <div class="row"><div class="label">Kembalian</div><div class="value">{{ $fmt($sale->change_amount) }}</div></div>
        </div>

        <div class="footer">Terima kasih telah berbelanja.</div>
    </div>
</div>
</body>
</html>
