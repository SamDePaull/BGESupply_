<?php

namespace App\Filament\Resources\SaleResource\Widgets;

use App\Filament\Resources\SaleResource;
use App\Models\Sale;
use Filament\Widgets\Widget;
use Illuminate\Support\Arr;

class SaleStatusTabs extends Widget
{
    protected static ?string $resource = SaleResource::class;
    protected static string $view = 'filament.sales.status-tabs';

    /** NON-STATIC, mengikuti parent */
    public function getColumnSpan(): int|string|array
    {
        return 'full';
    }

    /** Hitung badge per status sekali per render */
    protected function getCounts(): array
    {
        $grouped = Sale::query()
            ->selectRaw('status, COUNT(*) as c')
            ->groupBy('status')
            ->pluck('c', 'status');

        $paid     = (int) ($grouped['paid'] ?? 0);
        $unpaid   = (int) ($grouped['unpaid'] ?? 0);
        $refunded = (int) ($grouped['refunded'] ?? 0);
        $void     = (int) ($grouped['void'] ?? 0);

        return [
            'all'      => $paid + $unpaid + $refunded + $void,
            'paid'     => $paid,
            'unpaid'   => $unpaid,
            'refunded' => $refunded,
            'void'     => $void,
        ];
    }

    /** Oper semua data ke Blade secara eksplisit (anti-stale) */
    protected function getViewData(): array
    {
        $baseUrl        = static::$resource::getUrl();       // URL list sales tanpa query
        $currentStatus  = request()->query('status');        // null|'paid'|'unpaid'|'refunded'|'void'
        $persistedQuery = Arr::except(request()->query(), [
            'status',   // akan kita ganti
            'page',     // reset pagination saat ganti tab
        ]);

        return [
            'baseUrl'        => $baseUrl,
            'counts'         => $this->getCounts(),
            'currentStatus'  => $currentStatus,
            'persistedQuery' => $persistedQuery,
        ];
    }
}
