<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Setting extends Model
{
    protected $fillable = ['key', 'value'];
    protected $casts = [
        // kalau kamu simpan integer, boleh 'value' => 'integer'
        // kalau kadang simpan array, pakai 'array'
        'value' => 'integer',
    ];

    public static function get(string $key, $default = null)
    {
        $row = static::query()->where('key', $key)->first();
        return $row?->value ?? $default;
    }

    public static function set(string $key, $value): void
    {
        // gunakan Eloquent -> casting jalan dengan benar
        $row = static::firstOrNew(['key' => $key]);
        $row->value = $value; // integer
        $row->save();
    }
}
