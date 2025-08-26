<?php

namespace App\Filament\Pages;

use App\Models\Setting;
use App\Services\ShopifyInventoryService;
use Filament\Forms;
use Filament\Pages\Page;
use Filament\Notifications\Notification;

class ShopifySettings extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-cog-6-tooth';
    protected static string $view = 'filament.pages.shopify-settings';
    protected static ?string $navigationGroup = 'Settings';
    protected static ?string $title = 'Shopify Settings';
    public ?int $location_id = null;
    public function mount(): void
    {
        $saved = Setting::get('shopify.location_id');
        $this->location_id = is_array($saved) ? ($saved['id'] ?? null) : (is_numeric($saved) ? (int)$saved : null);
    }
    protected function getFormSchema(): array
    {
        $svc = app(ShopifyInventoryService::class);
        $options = [];
        foreach ($svc->listLocations() as $loc) {
            $label = $loc['name'];
            if (!empty($loc['primary'])) $label .= ' (primary)';
            $options[$loc['id']] = $label;
        }
        return [
            Forms\Components\Select::make('location_id')
                ->label('Default Shopify Location')
                ->options($options)
                ->searchable()
                ->required(),
        ];
    }
    public function save(): void
    {
        if (!$this->location_id) {
            Notification::make()->danger()->title('Select a location')->send();
            return;
        }

        // simpan sebagai integer saja
        Setting::set('shopify.location_id', (int) $this->location_id);

        Notification::make()->success()->title('Default location saved')->send();
    }
}
