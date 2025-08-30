<?php

namespace App\Filament\Pages;

use App\Models\Setting;
use App\Services\ShopifyInventoryService;
use Filament\Forms;
use Filament\Pages\Page;

class ShopifySettings extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-cog-6-tooth';
    protected static ?string $navigationGroup = 'Shopify';
    protected static string $view = 'filament.pages.shopify-settings';
    protected static ?string $title = 'Shopify Settings';

    public ?int $location_id = null;

    protected function getFormSchema(): array
    {
        return [
            Forms\Components\Select::make('location_id')
                ->label('Default Location')
                ->required()
                ->options(function () {
                    $svc = app(ShopifyInventoryService::class);
                    $out = [];
                    foreach ($svc->listLocations() as $l) {
                        $id = (int)($l['id'] ?? 0);
                        $name = (string)($l['name'] ?? 'Unknown');
                        $active = !empty($l['active']) ? '' : ' (inactive)';
                        if ($id) $out[$id] = "{$name}{$active}";
                    }
                    return $out;
                })
                ->searchable()
                ->preload()
                ->helperText('Lokasi ini akan dipakai untuk push stok (inventory_levels/set).'),
        ];
    }

    public function mount(): void
    {
        $val = Setting::get('shopify.location_id');
        $this->location_id = is_array($val) ? (int)($val['id'] ?? 0) : (int)($val ?? 0);
    }

    public function save(): void
    {
        $id = (int)$this->location_id;
        $name = (string)collect(app(ShopifyInventoryService::class)->listLocations())
            ->firstWhere('id', $id)['name'] ?? 'Unknown';

        Setting::set('shopify.location_id', ['id' => $id, 'name' => $name]);
        $this->dispatch('notification', type: 'success', title: 'Tersimpan', body: "Default location: {$name} ({$id})");
    }
}
