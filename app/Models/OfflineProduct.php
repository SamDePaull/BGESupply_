<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OfflineProduct extends Model
{
    protected $fillable = [
        'name','sku','price','cost_price','stock','image_url','attributes',
    ];

    protected $casts = [
        'attributes' => 'array',
    ];

    // Helper untuk duplikasi ke tabel unified products
    public function toUnifiedArray(): array
    {
        return [
            'name'            => $this->name,
            'sku'             => $this->sku,
            'price'           => $this->price,
            'cost_price'      => $this->cost_price,
            'stock'           => $this->stock,
            'image_url'       => $this->image_url,
            'origin'          => 'offline',
            'origin_id'       => $this->id,
            'is_from_shopify' => false,
            'sync_status'     => 'pending',
        ];
    }
}
