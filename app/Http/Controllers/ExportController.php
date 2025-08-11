<?php

namespace App\Http\Controllers;

use App\Models\Product;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ExportController extends Controller
{
    public function productsCsv(): StreamedResponse
    {
        $filename = 'products_' . now()->format('Ymd_His') . '.csv';

        $columns = ['id','name','sku','price','cost_price','stock','origin','shopify_product_id','sync_status','updated_at'];

        $callback = function() use ($columns) {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, $columns);

            Product::orderBy('id')->chunk(500, function ($rows) use ($handle, $columns) {
                foreach ($rows as $r) {
                    fputcsv($handle, [
                        $r->id, $r->name, $r->sku, $r->price, $r->cost_price, $r->stock,
                        $r->origin, $r->shopify_product_id, $r->sync_status, $r->updated_at,
                    ]);
                }
            });

            fclose($handle);
        };

        return response()->streamDownload($callback, $filename, [
            'Content-Type' => 'text/csv',
        ]);
    }
}
