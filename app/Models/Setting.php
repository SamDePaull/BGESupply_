<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Setting extends Model
{
    protected $fillable = ['key', 'value'];
    protected $casts = ['value' => 'array'];
    public static function get(string $key, $default = null)
    {
        $row = static::query()->where('key', $key)->first();
        return $row?->value ?? $default;
    }
    public static function set(string $key, $value): void
    {
        $row = static::firstOrNew(['key' => $key]);
        $row->value = $value;
        $row->save();
    }
}
