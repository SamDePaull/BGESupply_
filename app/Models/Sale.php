<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Sale extends Model
{
    protected $fillable = [
        'invoice_number','total','payment_method',
    ];

    public function items()
    {
        return $this->hasMany(SaleItem::class);
    }
}
