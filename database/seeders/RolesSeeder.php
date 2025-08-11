<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;

class RolesSeeder extends Seeder
{
    public function run(): void
    {
        $perms = [
            'products.view','products.create','products.update','products.delete','products.export',
            'offline_products.view','offline_products.create','offline_products.update','offline_products.delete',
            'sales.view','sales.create','sales.update','sales.delete',
            'shopify.push','shopify.refresh',
        ];

        foreach ($perms as $p) {
            Permission::findOrCreate($p, 'web'); // ← guard 'web'
        }

        $admin   = Role::findOrCreate('admin', 'web');   // ← guard 'web'
        $cashier = Role::findOrCreate('cashier', 'web'); // ← guard 'web'

        $admin->givePermissionTo($perms);

        $cashier->givePermissionTo([
            'products.view',
            'offline_products.view','offline_products.create',
            'sales.view','sales.create',
            // tambahkan 'shopify.push' kalau kasir boleh push
        ]);
    }
}
