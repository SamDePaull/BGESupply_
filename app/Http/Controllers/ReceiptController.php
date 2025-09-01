<?php

namespace App\Http\Controllers;

use App\Models\Sale;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

// gunakan facade Pdf jika tersedia
use Barryvdh\DomPDF\Facade\Pdf;

class ReceiptController extends Controller
{
    public function show(Sale $sale)
    {
        return view('receipts.sale', [
            'sale'  => $sale->load('items'),
            'asPdf' => false,
        ]);
    }

    public function pdf(Sale $sale)
    {
        // Jika paket dompdf belum terpasang, alihkan ke preview HTML (tidak memaksa download)
        if (! class_exists(Pdf::class)) {
            return redirect()->route('receipt.show', $sale)
                ->with('warning', 'PDF engine belum terpasang. Menampilkan pratinjau HTML.');
        }

        // Siapkan data
        $sale->load('items');
        $view = view('receipts.sale', ['sale' => $sale, 'asPdf' => true])->render();

        // Render PDF
        $pdf = Pdf::setOptions([
                'isHtml5ParserEnabled' => true,
                'isRemoteEnabled'      => true,
            ])
            ->loadHTML($view)
            ->setPaper('a5', 'portrait');

        $filename = 'Receipt-' . ($sale->number ?? $sale->id) . '.pdf';

        // --- Perbaikan download:
        // 1) Pastikan tidak ada output lain yang “bocor” (BOM/echo) yang merusak header
        if (ob_get_length()) {
            @ob_end_clean();
        }
        // 2) Matikan kompresi output yang kadang bikin "Failed - network error"
        if (function_exists('ini_set')) {
            @ini_set('zlib.output_compression', '0');
        }

        // 3) Kirim dengan streamDownload supaya browser selalu treat sebagai file
        $content = $pdf->output();

        return response()->streamDownload(
            function () use ($content) {
                echo $content;
            },
            $filename,
            [
                'Content-Type'              => 'application/pdf',
                'Content-Disposition'       => 'attachment; filename="'.$filename.'"',
                'Cache-Control'             => 'no-store, no-cache, must-revalidate, max-age=0',
                'Pragma'                    => 'no-cache',
                'Expires'                   => '0',
                'X-Content-Type-Options'    => 'nosniff',
            ]
        );
    }
}
