<?php

namespace App\Support\Filament;

class Perm
{
    public static function can(string $permission): bool
    {
        $u = auth()->user();
        return $u?->can($permission) ?? false;
    }
}
