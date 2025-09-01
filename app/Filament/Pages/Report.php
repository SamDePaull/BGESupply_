<?php

namespace App\Filament\Pages;

use Filament\Forms;
use Filament\Forms\Components\Grid;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Actions as FormActions;
use Filament\Pages\Page;

class Report extends Page implements Forms\Contracts\HasForms
{
    use Forms\Concerns\InteractsWithForms;

    protected static ?string $navigationIcon  = 'heroicon-o-rectangle-stack';
    // protected static ?string $navigationLabel = 'Laporan';
    protected static ?string $navigationGroup = 'Laporan';
    protected static ?int    $navigationSort  = 1;

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
        $months = [1=>'Jan',2=>'Feb',3=>'Mar',4=>'Apr',5=>'Mei',6=>'Jun',7=>'Jul',8=>'Agu',9=>'Sep',10=>'Okt',11=>'Nov',12=>'Des'];
        $years  = collect(range((int)now()->year - 5, (int)now()->year + 1))->mapWithKeys(fn($y)=>[$y=>$y])->all();

        return $form
            ->statePath('data')
            ->schema([
                Section::make('Filter Periode')->schema([
                    Grid::make(4)->schema([
                        Select::make('from_month')->label('Dari Bulan')->options($months)->required(),
                        Select::make('from_year')->label('Dari Tahun')->options($years)->required(),
                        Select::make('to_month')->label('Sampai Bulan')->options($months)->required(),
                        Select::make('to_year')->label('Sampai Tahun')->options($years)->required(),
                    ]),
                    FormActions::make([
                        FormActions\Action::make('download')
                            ->label('Download PDF (Gabungan)')
                            ->icon('heroicon-o-arrow-down-tray')
                            ->color('primary')
                            ->action(function () {
                                $s = $this->form->getState();
                                $url = route('reports.all.pdf', [
                                    'from_month' => $s['from_month'] ?? now()->month,
                                    'from_year'  => $s['from_year']  ?? now()->year,
                                    'to_month'   => $s['to_month']   ?? now()->month,
                                    'to_year'    => $s['to_year']    ?? now()->year,
                                ]);
                                return redirect()->to($url);
                            }),
                    ])->alignEnd(),
                ])->collapsible(false),
            ]);
    }
}
