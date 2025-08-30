<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OfflineProductStaging extends Model
{
    protected $table = 'offline_product_staging';
    protected $guarded = [];
    protected $casts = [
        'options' => 'array',
        'variants' => 'array',
        'images' => 'array',
    ];
}
