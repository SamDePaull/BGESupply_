<?php

namespace App\Filament\Pages;

use App\Models\Sale;
use App\Models\ProductVariant;
use Carbon\Carbon;
use Filament\Forms;
use Filament\Forms\Components\Grid;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\View as ViewField;
use Filament\Forms\Components\Actions as FormActions;
use Filament\Forms\Get;
use Filament\Pages\Page;

class Report extends Page implements Forms\Contracts\HasForms
{
    use Forms\Concerns\InteractsWithForms;

    protected static ?string $navigationIcon  = 'heroicon-o-rectangle-stack';
    protected static ?string $navigationLabel = 'Laporan';
    protected static ?int    $navigationSort  = 4;

    // shell yang sudah kamu pakai
    protected static string $view = 'filament.pages.form-shell';

    public ?array $data = [];

    public function mount(): void
    {
        $now = now();
        $this->form->fill([
            'from_month' => $now->month,
            'from_year'  => $now->year,
            'to_month'   => $now->month,
            'to_year'    => $now->year,
        ]);
    }

    public function form(Forms\Form $form): Forms\Form
    {
        $months = [
            1 => 'Januari',
            2 => 'Februari',
            3 => 'Maret',
            4 => 'April',
            5 => 'Mei',
            6 => 'Juni',
            7 => 'Juli',
            8 => 'Agustus',
            9 => 'September',
            10 => 'Oktober',
            11 => 'November',
            12 => 'Desember'
        ];
        $years  = collect(range((int) now()->year - 5, (int) now()->year + 1))
            ->mapWithKeys(fn($y) => [$y => $y])->all();

        return $form
            ->statePath('data')
            ->schema([
                // === 2 kolom: kiri Visualisasi, kanan Filter (sticky) ===
                Grid::make()
                    ->schema([
                        // KIRI: VISUALISASI
                        Section::make('Visualisasi')
                            ->schema([
                                ViewField::make('charts')
                                    ->view('filament.pages.report.charts')
                                    ->reactive()
                                    ->viewData(fn(Get $get) => $this->computeChartsData($get)),
                            ])
                            ->compact() // padding lebih ringkas
                            ->columnSpan([
                                'default' => 12,   // mobile 1 kolom
                                'lg'      => 8,    // layar besar 8/12
                                'xl'      => 9,    // layar ekstra besar 9/12
                            ]),

                        // KANAN: FILTER PERIODE (sticky)
                        Section::make('Filter Periode')
                            ->schema([
                                Grid::make(1)->schema([
                                    Select::make('from_month')->label('Dari Bulan')->options($months)->required()->live(),
                                    Select::make('from_year')->label('Dari Tahun')->options($years)->required()->live(),
                                    Select::make('to_month')->label('Sampai Bulan')->options($months)->required()->live(),
                                    Select::make('to_year')->label('Sampai Tahun')->options($years)->required()->live(),
                                ])->columns(1),
                            ])

                            ->headerActions([
                                // Preview: ikon + tooltip, open in new tab
                                FormActions\Action::make('preview')
                                    ->label('') // ikon saja
                                    ->icon('heroicon-o-eye')
                                    ->color('warning')
                                    ->tooltip('Preview PDF')
                                    ->url(function () {
                                        $s = $this->data ?? [];
                                        return route('reports.all.pdf', [
                                            'from_month' => (int) ($s['from_month'] ?? now()->month),
                                            'from_year'  => (int) ($s['from_year']  ?? now()->year),
                                            'to_month'   => (int) ($s['to_month']   ?? now()->month),
                                            'to_year'    => (int) ($s['to_year']    ?? now()->year),
                                        ]);
                                    }, shouldOpenInNewTab: true)
                                    ->iconButton()
                                    ->extraAttributes(['class' => 'ml-1 !w-auto']),

                                // Unduh: ikon + tooltip, open in new tab
                                FormActions\Action::make('download')
                                    ->label('')
                                    ->icon('heroicon-o-arrow-down-tray')
                                    ->color('gray')
                                    ->tooltip('Unduh PDF')
                                    ->url(function () {
                                        $s = $this->data ?? [];
                                        return route('reports.all.pdf', [
                                            'from_month' => (int) ($s['from_month'] ?? now()->month),
                                            'from_year'  => (int) ($s['from_year']  ?? now()->year),
                                            'to_month'   => (int) ($s['to_month']   ?? now()->month),
                                            'to_year'    => (int) ($s['to_year']    ?? now()->year),
                                            'download'   => 1,
                                        ]);
                                    }, shouldOpenInNewTab: true)
                                    ->iconButton()
                                    ->extraAttributes(['class' => 'ml-1 !w-auto']),
                            ])
                            ->compact()
                            ->extraAttributes([
                                // bikin sidebar nempel saat scroll
                                'class' => 'lg:sticky lg:top-6',
                            ])
                            ->columnSpan([
                                'default' => 12, // mobile di bawah
                                'lg'      => 4,  // 4/12 di kanan
                                'xl'      => 3,  // 3/12 di kanan
                            ]),
                    ])->columns(12),
            ]);
    }

    /** Hitung KPI & data grafik berdasarkan filter terkini */
    protected function computeChartsData(Get $get): array
    {
        $tz = config('app.timezone', 'Asia/Jakarta');

        $fm = (int) ($get('from_month') ?: now()->month);
        $fy = (int) ($get('from_year')  ?: now()->year);
        $tm = (int) ($get('to_month')   ?: now()->month);
        $ty = (int) ($get('to_year')    ?: now()->year);

        $from = Carbon::createFromDate($fy, $fm, 1, $tz)->startOfMonth();
        $to   = Carbon::createFromDate($ty, $tm, 1, $tz)->endOfMonth();

        if ($from->gt($to)) {
            [$from, $to] = [$to->copy()->startOfMonth(), $from->copy()->endOfMonth()];
        }

        $label = $from->translatedFormat('F Y') . ' – ' . $to->translatedFormat('F Y');

        // Sales (semua status)
        $sales = Sale::whereBetween('created_at', [$from, $to])->get();
        $kpi = [
            'orders' => $sales->count(),
            'revenue' => (float) $sales->sum('total'),
            'aov'    => $sales->count() ? (float) $sales->avg('total') : 0.0,
            'sku'    => ProductVariant::count(),
        ];

        // Revenue per bulan (paid)
        $paid = $sales->where('status', 'paid')->values();
        $map = [];
        $cursor = $from->copy()->startOfMonth();
        while ($cursor <= $to) {
            $map[$cursor->format('Y-m')] = 0.0;
            $cursor->addMonth();
        }
        foreach ($paid as $s) {
            $k = $s->created_at->format('Y-m');
            if (isset($map[$k])) $map[$k] += (float) $s->total;
        }
        $revLabels = [];
        $revValues = [];
        $revMax = 0.0;
        foreach ($map as $ym => $sum) {
            $m = Carbon::createFromFormat('Y-m', $ym);
            $revLabels[] = $m->translatedFormat('M Y');
            $revValues[] = $sum;
            $revMax = max($revMax, $sum);
        }

        // Metode pembayaran (paid)
        $byMethod = [];
        foreach ($paid as $s) {
            $m = (string) ($s->payment_method ?? 'other');
            $byMethod[$m] = ($byMethod[$m] ?? 0) + (float) $s->total;
        }
        arsort($byMethod);
        $mLabels = [];
        $mValues = [];
        foreach ($byMethod as $k => $v) {
            $mLabels[] = match ($k) {
                'cash' => 'Cash',
                'qris' => 'QRIS',
                'transfer' => 'Transfer',
                'card' => 'Card',
                default => ucfirst($k),
            };
            $mValues[] = $v;
        }

        return [
            'label'   => $label,
            'kpi'     => $kpi,
            'revenue' => [
                'labels' => $revLabels,
                'values' => $revValues,
                'total'  => array_sum($revValues),
                'max'    => $revMax,
            ],
            'methods' => [
                'labels' => $mLabels,
                'values' => $mValues,
            ],
        ];
    }
}
