<html>

<body>
    <h3>Invoice #{{ $sale->invoice_no }}</h3>
    <p>Tanggal: {{ $sale->created_at }}</p>
    <table width="100%" border="1" cellpadding="6" cellspacing="0">
        <tr>
            <th>Item</th>
            <th>Qty</th>
            <th>Harga</th>
            <th>Subtotal</th>
        </tr>
        @foreach($sale->items as $it)
        <tr>
            <td>{{ $it->title }}</td>
            <td>{{ $it->qty }}</td>
            <td>Rp {{ number_format($it->price,0,',','.') }}</td>
            <td>Rp {{ number_format($it->subtotal,0,',','.') }}</td>
        </tr>
        @endforeach
        <tr>
            <td colspan="3" align="right"><b>Total</b></td>
            <td><b>Rp {{ number_format($sale->total,0,',','.') }}</b></td>
        </tr>
    </table>
</body>

</html>
