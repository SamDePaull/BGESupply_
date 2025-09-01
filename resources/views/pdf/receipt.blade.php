<html>
<head>
    <meta charset="utf-8" />
    <style>
        * { font-family: DejaVu Sans, Arial, sans-serif; font-size: 12px; }
        h3 { margin: 0 0 8px; }
        .meta { margin: 0 0 12px; color: #444; }
        table { width:100%; border-collapse: collapse; }
        th, td { padding: 8px; border: 1px solid #ddd; }
        th { background: #f7f7f7; text-align: left; }
        tfoot td { font-weight: 700; }
        .num { text-align: right; white-space: nowrap; }
    </style>
</head>
<body>
    <h3>Invoice #{{ $sale->invoice_no }}</h3>
    <p class="meta">Tanggal: {{ $sale->created_at->format('d M Y H:i') }}</p>
    <table>
        <thead>
            <tr>
                <th>Item</th><th class="num">Qty</th><th class="num">Harga</th><th class="num">Subtotal</th>
            </tr>
        </thead>
        <tbody>
            @foreach($sale->items as $it)
            <tr>
                <td>{{ $it->title }}</td>
                <td class="num">{{ $it->qty }}</td>
                <td class="num">Rp {{ number_format($it->price,0,',','.') }}</td>
                <td class="num">Rp {{ number_format($it->subtotal,0,',','.') }}</td>
            </tr>
            @endforeach
        </tbody>
        <tfoot>
            <tr>
                <td colspan="3" class="num">Total</td>
                <td class="num">Rp {{ number_format($sale->total,0,',','.') }}</td>
            </tr>
        </tfoot>
    </table>
</body>
</html>
