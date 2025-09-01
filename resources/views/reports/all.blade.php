@php
$fmt = fn($n) => 'Rp ' . number_format((float)$n, 0, ',', '.');
@endphp
<!doctype html>
<html>
<head>
<meta charset="utf-8">
<title>{{ config('app.name','BGE_Supply') }} — Laporan (periode {{ $from->translatedFormat('M Y') }} sampai periode {{ $to->translatedFormat('M Y') }})</title>
<style>
* { box-sizing: border-box; }
body { font-family: ui-sans-serif, system-ui, -apple-system, Segoe UI, Roboto, Arial, "Helvetica Neue", "Noto Sans", sans-serif; margin: 0; padding: 20px; color: #111827; }
h1 { font-size: 20px; margin: 0 0 10px; }
h2 { font-size: 16px; margin: 14px 0 8px; }
.muted { color: #6b7280; font-size: 12px; }

.grid { display: table; width: 100%; table-layout: fixed; margin: 10px 0; }
.grid .cell { display: table-cell; padding: 0 5px; vertical-align: top; }
.card { border: 1px solid #e5e7eb; border-radius: 12px; padding: 10px; }
.kpi .label { font-size: 12px; color: #6b7280; }
.kpi .value { font-size: 16px; font-weight: 700; }
.small { font-size: 11px; color: #6b7280; }

.section { margin-top: 14px; }

table { width: 100%; border-collapse: collapse; margin-top: 8px; }
th, td { border-bottom: 1px solid #f3f4f6; text-align: left; font-size: 12px; padding: 6px 4px; }
th.num, td.num { text-align: right; }

.badge { display: inline-block; padding: 2px 6px; border-radius: 999px; font-size: 11px; background: #eef2ff; color: #3730a3; }

.bar-row { display: table; width: 100%; margin: 2px 0; }
.bar-label { display: table-cell; width: 130px; font-size: 12px; color: #374151; vertical-align: middle; }
.bar-wrap { display: table-cell; width: 1%; vertical-align: middle; }
.bar { height: 10px; background: #6366F1; border-radius: 999px; }

tfoot th, tfoot td { font-weight: 700; }
thead th { font-weight: 700; color: #374151; }
</style>
</head>
<body>

  {{-- ========= Header + KPI (Halaman 1) ========= --}}
  <h1>{{ config('app.name','BGE_Supply') }} — Laporan (periode {{ $from->translatedFormat('M Y') }} sampai periode {{ $to->translatedFormat('M Y') }})</h1>
  <div class="muted">Ringkasan KPI</div>

  <div class="grid">
    <div class="cell">
      <div class="card kpi">
        <div class="label">Total Order</div>
        <div class="value">{{ $salesTotals['count'] }}</div>
        <div class="small">Avg. Order: {{ $fmt($avgOrder) }}</div>
      </div>
    </div>
    <div class="cell">
      <div class="card kpi">
        <div class="label">Total Pendapatan</div>
        <div class="value">{{ $fmt($salesTotals['total']) }}</div>
        <div class="small">Subtotal: {{ $fmt($salesTotals['subtotal']) }} • Diskon: {{ $fmt($salesTotals['discount']) }} • Pajak: {{ $fmt($salesTotals['tax']) }}</div>
      </div>
    </div>
    <div class="cell">
      <div class="card kpi">
        <div class="label">Stok Saat Ini</div>
        <div class="value">{{ $stockTotalUnits }} unit</div>
        <div class="small">{{ $stockTotalSku }} SKU • Low stock (&lt;5): {{ $lowStockCount }}</div>
      </div>
    </div>
  </div>

  <!-- ===== Halaman 1: Ringkasan Penjualan ===== -->
  <div class="section">
    <h2>1) Ringkasan Penjualan</h2>
    <table>
      <thead>
        <tr>
          <th>Tanggal</th>
          <th>No. Nota</th>
          <th>Pelanggan</th>
          <th>Metode</th>
          <th>Status</th>
          <th class="num">Total</th>
        </tr>
      </thead>
      <tbody>
        @foreach ($sales as $s)
          <tr>
            <td>{{ $s->created_at?->format('d M Y H:i') }}</td>
            <td>{{ $s->number ?? $s->id }}</td>
            <td>{{ $s->customer_name ?: '-' }}</td>
            <td>{{ strtoupper($s->payment_method ?? '-') }}</td>
            <td><span class="badge">{{ ucfirst($s->status) }}</span></td>
            <td class="num">{{ $fmt($s->total) }}</td>
          </tr>
        @endforeach
      </tbody>
      <tfoot>
        <tr>
          <th colspan="5" class="num">Total</th>
          <th class="num">{{ $fmt($salesTotals['total']) }}</th>
        </tr>
      </tfoot>
    </table>
  </div>

  {{-- ======= PAGE BREAK: Pendapatan per Bulan di halaman baru ======= --}}
  <div style="page-break-before: always;"></div>

  <!-- ===== Halaman 2: Pendapatan per Bulan (Paid) ===== -->
  <h1>{{ config('app.name','BGE_Supply') }} — Laporan (periode {{ $from->translatedFormat('M Y') }} sampai periode {{ $to->translatedFormat('M Y') }})</h1>
  <div class="muted">Bagian 2/3</div>

  <div class="section">
    <h2>2) Pendapatan per Bulan (Paid)</h2>
    @php $maxBarPx = 180; @endphp
    @foreach ($revenueRows as $row)
      @php
        $barPx = $revenueMax > 0 ? (int) round(($row['amount'] / $revenueMax) * $maxBarPx) : 0;
        if ($row['amount'] > 0 && $barPx === 0) { $barPx = 1; }
      @endphp
      <div class="bar-row">
        <div class="bar-label">{{ $row['label'] }}</div>
        <div class="bar-wrap">
          <div class="bar" style="width: <?= e($barPx) ?>px;"></div>
        </div>
        <div class="small" style="display: table-cell; width: 100px; text-align: right;">{{ $fmt($row['amount']) }}</div>
      </div>
    @endforeach
    <div class="small" style="text-align: right; margin-top: 4px;"><strong>Total:</strong> {{ $fmt($revenueGrand) }}</div>
  </div>

  {{-- ======= PAGE BREAK: Stok di halaman baru ======= --}}
  <div style="page-break-before: always;"></div>

  <!-- ===== Halaman 3: Stok Saat Ini ===== -->
  <h1>{{ config('app.name','BGE_Supply') }} — Laporan (periode {{ $from->translatedFormat('M Y') }} sampai periode {{ $to->translatedFormat('M Y') }})</h1>
  <div class="muted">Bagian 3/3</div>

  <div class="section">
    <h2>3) Stok Saat Ini</h2>
    <table>
      <thead>
        <tr>
          <th>Produk / Varian</th>
          <th>SKU</th>
          <th class="num">Stok</th>
        </tr>
      </thead>
      <tbody>
        @foreach ($variants as $v)
          @php
            $isDefault = $v->title === null || $v->title === '' || strcasecmp($v->title, 'Default Title')===0 || strcasecmp($v->title,'Default')===0;
            $name = trim(($v->product->title ?? '') . ($isDefault ? '' : (' / '.$v->title)));
          @endphp
          <tr>
            <td>{{ $name }}</td>
            <td>{{ $v->sku ?: '-' }}</td>
            <td class="num">{{ (int) ($v->inventory_quantity ?? 0) }}</td>
          </tr>
        @endforeach
      </tbody>
    </table>
  </div>

</body>
</html>
