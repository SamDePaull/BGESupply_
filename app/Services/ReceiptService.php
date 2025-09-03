<?php

namespace App\Services;

use App\Models\Sale;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Storage;

class ReceiptService
{
    public function generatePdf(Sale $sale): string
    {
        // $pdf = Pdf::loadView('pdf.receipt', ['sale' => $sale->load('items')]);
        // $path = "receipts/INV-{$sale->invoice_no}.pdf";
        // Storage::disk('public')->put($path, $pdf->output());
        // return asset("storage/{$path}");
         return route('receipt.pdf', ['sale' => $sale->getKey()]);
    }
}
