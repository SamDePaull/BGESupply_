<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProductImage extends Model
{
    protected $fillable = ['product_id', 'file_path', 'alt', 'position', 'is_primary', 'shopify_image_id'];
    protected $casts = ['is_primary' => 'boolean'];


    public function setFilePathAttribute($value): void
    {
        // Normalisasi string
        if (is_string($value)) {
            $value = trim($value);
        }

        // Jika nilai baru null/empty dan kita sudah punya nilai lama, JANGAN timpa.
        if (($value === null || $value === '') &&
            array_key_exists('file_path', $this->attributes) &&
            !empty($this->attributes['file_path'])
        ) {
            return;
        }

        $this->attributes['file_path'] = $value;
    }
    public function product()
    {
        return $this->belongsTo(Product::class);
    }
}
