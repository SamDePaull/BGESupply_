<?php

namespace App\Http\Controllers;

use App\Models\Sale;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Barryvdh\DomPDF\Facade\Pdf;

class ReceiptController extends Controller
{
    public function show(Sale $sale)
    {
        return view('receipts.sale', [
            'sale' => $sale->load('items'),
        ]);
    }

    public function pdf(Sale $sale)
    {
        // Jika paket dompdf belum terpasang → fallback ke HTML
        if (! class_exists(Pdf::class)) {
            return redirect()
                ->route('receipt.show', $sale)
                ->with('warning', 'PDF engine belum terpasang. Menampilkan pratinjau HTML.');
        }

        // Data + render HTML
        $sale->load('items');
        $html = view('receipts.sale', ['sale' => $sale, 'asPdf' => true])->render();

        // Render PDF (tanpa menyimpan file di storage)
        $pdf = Pdf::setOptions([
                'isHtml5ParserEnabled' => true,
                'isRemoteEnabled'      => true,
            ])
            ->loadHTML($html)
            ->setPaper('a5', 'portrait');

        $filename = 'Receipt-' . ($sale->number ?? $sale->id) . '.pdf';

        // Hindari output bocor & kompresi
        if (ob_get_length()) { @ob_end_clean(); }
        if (function_exists('ini_set')) { @ini_set('zlib.output_compression', '0'); }

        $content = $pdf->output();

        // ⬇️ KUNCI: kirim sebagai inline agar PREVIEW di tab baru
        return response($content, 200, [
            'Content-Type'        => 'application/pdf',
            'Content-Disposition' => 'inline; filename="' . $filename . '"',
            'Cache-Control'       => 'private, must-revalidate, max-age=0',
            'Pragma'              => 'public',
            // Opsional: 'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
