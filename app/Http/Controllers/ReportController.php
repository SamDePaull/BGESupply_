<?php

namespace App\Http\Controllers;

use App\Models\ProductVariant;
use App\Models\Sale;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Http\Request;

class ReportController extends Controller
{
    private function parsePeriod(Request $r): array
    {
        $fromMonth = (int) $r->query('from_month', now()->month);
        $fromYear  = (int) $r->query('from_year',  now()->year);
        $toMonth   = (int) $r->query('to_month',   now()->month);
        $toYear    = (int) $r->query('to_year',    now()->year);

        $from = Carbon::create($fromYear, $fromMonth, 1, 0, 0, 0)->startOfDay();
        $to   = Carbon::create($toYear,   $toMonth,   1, 0, 0, 0)->endOfMonth()->endOfDay();

        if ($from->gt($to)) {
            [$from, $to] = [$to->copy()->startOfMonth()->startOfDay(), $from->copy()->endOfMonth()->endOfDay()];
        }

        $label = $from->translatedFormat('M Y') . ' – ' . $to->translatedFormat('M Y');
        return [$from, $to, $label];
    }

    /** === PDF GABUNGAN: Penjualan + Pendapatan + Stok === */
    public function all(Request $r)
    {
        [$from, $to, $label] = $this->parsePeriod($r);

        // ---------------- Penjualan (daftar transaksi) ----------------
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
            $key = $cursor->format('Y-m');
            $map[$key] = 0.0;
            $cursor->addMonth();
        }
        foreach ($paidSales as $s) {
            $key = $s->created_at->format('Y-m');
            if (array_key_exists($key, $map)) {
                $map[$key] += (float) $s->total;
            }
        }
        $revenueRows = [];
        $maxRevenue = 0.0;
        foreach ($map as $ym => $sum) {
            $m = Carbon::createFromFormat('Y-m', $ym);
            $revenueRows[] = [
                'label'  => $m->translatedFormat('F Y'),
                'amount' => $sum,
            ];
            $maxRevenue = max($maxRevenue, $sum);
        }
        $revenueGrand = array_sum(array_column($revenueRows, 'amount'));

        // ---------------- Stok (snapshot saat ini) ----------------
        // ---------------- Stok (snapshot saat ini) ----------------
        $variants = ProductVariant::with('product')
            ->leftJoin('products', 'products.id', '=', 'product_variants.product_id')
            ->orderBy('products.title')
            ->orderBy('product_variants.title')
            ->select('product_variants.*') // penting agar Eloquent meng-hydrate ProductVariant
            ->get();

        $stockTotalSku   = $variants->count();
        $stockTotalUnits = (int) $variants->sum(fn($v) => (int) ($v->inventory_quantity ?? 0));
        $lowStockCount   = (int) $variants->filter(fn($v) => (int) ($v->inventory_quantity ?? 0) < 5)->count();


        $pdf = Pdf::loadView('reports.all', [
            'label'          => $label,
            'from'           => $from,
            'to'             => $to,

            // sales
            'sales'          => $sales,
            'salesTotals'    => $salesTotals,
            'avgOrder'       => $avgOrder,

            // revenue
            'revenueRows'    => $revenueRows,
            'revenueGrand'   => $revenueGrand,
            'revenueMax'     => $maxRevenue,

            // stock
            'variants'       => $variants,
            'stockTotalSku'  => $stockTotalSku,
            'stockTotalUnits' => $stockTotalUnits,
            'lowStockCount'  => $lowStockCount,
        ])->setPaper('a4', 'portrait');

        return $pdf->download('laporan-gabungan-' . $from->format('Ym') . '-' . $to->format('Ym') . '.pdf');
    }
}
