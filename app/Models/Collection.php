<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Collection extends Model
{
    protected $fillable = [
        'shopify_collection_id', 'title', 'handle', 'type', 'body_html', 'published_at',
    ];
    protected $casts = [
        'published_at' => 'datetime',
    ];
}
