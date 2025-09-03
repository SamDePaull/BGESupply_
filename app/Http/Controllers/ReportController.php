<?php

namespace App\Http\Controllers;

use App\Models\ProductVariant;
use App\Models\Sale;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Http\Request;

class ReportController extends Controller
{
    /**
     * Terima dua skema input:
     *  A) from_month, from_year, to_month, to_year   (Select bulan & tahun)
     *  B) from, until                                 (DatePicker penuh)
     * Jika keduanya kosong → fallback ke bulan berjalan.
     */
    private function parsePeriod(Request $r): array
    {
        $tz = config('app.timezone', 'Asia/Jakarta');

        // --- Skema A: bulan/tahun (baca dari input(), bukan hanya query())
        $fm = (int) $r->input('from_month', now()->month);
        $fy = (int) $r->input('from_year',  now()->year);
        $tm = (int) $r->input('to_month',   now()->month);
        $ty = (int) $r->input('to_year',    now()->year);

        $from = null;
        $to   = null;

        if (
            $fm >= 1 && $fm <= 12 && $fy >= 2000 && $fy <= 2100 &&
            $tm >= 1 && $tm <= 12 && $ty >= 2000 && $ty <= 2100
        ) {
            $from = Carbon::createFromDate($fy, $fm, 1, $tz)->startOfMonth()->startOfDay();
            $to   = Carbon::createFromDate($ty, $tm, 1, $tz)->endOfMonth()->endOfDay();
        }

        // --- Skema B: from / until (DatePicker)
        if (!$from || !$to) {
            $f = $r->input('from');
            $u = $r->input('until');
            if (!empty($f) && !empty($u)) {
                try {
                    $from = Carbon::parse($f, $tz)->startOfMonth()->startOfDay();
                    $to   = Carbon::parse($u, $tz)->endOfMonth()->endOfDay();
                } catch (\Throwable $e) {
                    // abaikan, akan fallback
                }
            }
        }

        // --- Fallback terakhir: bulan berjalan
        if (!$from || !$to) {
            $now  = Carbon::now($tz);
            $from = $now->copy()->startOfMonth()->startOfDay();
            $to   = $now->copy()->endOfMonth()->endOfDay();
        }

        // --- Normalisasi jika user terbalik (to < from)
        if ($from->gt($to)) {
            [$from, $to] = [
                $to->copy()->startOfMonth()->startOfDay(),
                $from->copy()->endOfMonth()->endOfDay(),
            ];
        }

        // Label periode yang rapi (contoh: "Januari 2025 – Maret 2025")
        $label = $from->translatedFormat('F Y') . ' – ' . $to->translatedFormat('F Y');

        return [$from, $to, $label];
    }

    /** === PDF GABUNGAN: Penjualan + Pendapatan + Stok === */
    // ... (tetap pakai parsePeriod() milikmu)

    public function all(Request $r)
    {
        [$from, $to, $label] = $this->parsePeriod($r);

        // ---------------- Penjualan ----------------
        $sales = Sale::with('items')
            ->whereBetween('created_at', [$from, $to])
            ->orderBy('created_at')
            ->get();

        $salesTotals = [
            'count'    => $sales->count(),
            'subtotal' => (float) $sales->sum('subtotal'),
            'discount' => (float) $sales->sum('discount'),
            'tax'      => (float) $sales->sum('tax'),
            'total'    => (float) $sales->sum('total'),
            'paid'     => (float) $sales->sum('paid_amount'),
        ];
        $avgOrder = $salesTotals['count'] > 0 ? $salesTotals['total'] / $salesTotals['count'] : 0.0;

        // ---------------- Pendapatan per Bulan (status paid) ----------------
        $paidSales = $sales->where('status', 'paid')->values();

        // map bulan dari periode
        $map = [];
        $cursor = $from->copy()->startOfMonth();
        while ($cursor <= $to) {
            $map[$cursor->format('Y-m')] = 0.0;
            $cursor->addMonth();
        }
        foreach ($paidSales as $s) {
            $key = $s->created_at->format('Y-m');
            if (isset($map[$key])) {
                $map[$key] += (float) $s->total;
            }
        }
        $revenueRows = [];
        $maxRevenue = 0.0;
        foreach ($map as $ym => $sum) {
            $m = Carbon::createFromFormat('Y-m', $ym);
            $revenueRows[] = [
                'label'  => $m->translatedFormat('M Y'),
                'amount' => $sum,
            ];
            $maxRevenue = max($maxRevenue, $sum);
        }
        $revenueGrand = array_sum(array_column($revenueRows, 'amount'));

        // ---------------- Metode Pembayaran (status paid) ----------------
        $methodSum = [];
        foreach ($paidSales as $s) {
            $key = (string)($s->payment_method ?? 'other');
            $methodSum[$key] = ($methodSum[$key] ?? 0.0) + (float)$s->total;
        }
        // urutkan desc
        arsort($methodSum);
        $methodTotal = array_sum($methodSum);
        $methodRows = [];
        foreach ($methodSum as $method => $sum) {
            $methodRows[] = [
                'label'    => match ($method) {
                    'cash' => 'Cash',
                    'qris' => 'QRIS',
                    'transfer' => 'Transfer',
                    'card' => 'Card',
                    default => ucfirst($method),
                },
                'amount'   => $sum,
                'percent'  => $methodTotal > 0 ? round($sum / $methodTotal * 100, 1) : 0.0,
            ];
        }

        // ---------------- Stok (snapshot) ----------------
        $variants = ProductVariant::with('product')
            ->leftJoin('products', 'products.id', '=', 'product_variants.product_id')
            ->orderBy('products.title')
            ->orderBy('product_variants.title')
            ->select('product_variants.*')
            ->get();

        $stockTotalSku    = $variants->count();
        $stockTotalUnits  = (int) $variants->sum(fn($v) => (int) ($v->inventory_quantity ?? 0));
        $lowStockCount    = (int) $variants->filter(fn($v) => (int) ($v->inventory_quantity ?? 0) < 5)->count();

        // --- Render PDF INLINE
        $pdf = Pdf::setOptions([
            'isHtml5ParserEnabled' => true,
            'isRemoteEnabled'      => true,
        ])
            ->loadView('reports.all', [
                'label'            => $label,
                'from'             => $from,
                'to'               => $to,

                // sales
                'sales'            => $sales,
                'salesTotals'      => $salesTotals,
                'avgOrder'         => $avgOrder,

                // revenue
                'revenueRows'      => $revenueRows,
                'revenueGrand'     => $revenueGrand,
                'revenueMax'       => $maxRevenue,

                // methods
                'methodRows'       => $methodRows,
                'methodTotal'      => $methodTotal,

                // stock
                'variants'         => $variants,
                'stockTotalSku'    => $stockTotalSku,
                'stockTotalUnits'  => $stockTotalUnits,
                'lowStockCount'    => $lowStockCount,
            ])
            ->setPaper('a4', 'portrait');

        $filename = 'laporan-gabungan-' . $from->format('Ym') . '-' . $to->format('Ym') . '.pdf';
        if (ob_get_length()) {
            @ob_end_clean();
        }

        return response($pdf->output(), 200, [
            'Content-Type'        => 'application/pdf',
            'Content-Disposition' => 'inline; filename="' . $filename . '"',
        ]);
    }
}
