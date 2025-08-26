<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        // Set semua NULL -> 0 (products & product_variants)
        DB::statement("UPDATE products SET inventory_quantity = 0 WHERE inventory_quantity IS NULL");
        DB::statement("UPDATE product_variants SET inventory_quantity = 0 WHERE inventory_quantity IS NULL");

        // Pastikan DEFAULT 0 dan NOT NULL (PostgreSQL)
        // products.inventory_quantity
        DB::statement('ALTER TABLE products ALTER COLUMN inventory_quantity SET DEFAULT 0');
        DB::statement('ALTER TABLE products ALTER COLUMN inventory_quantity SET NOT NULL');

        // product_variants.inventory_quantity
        DB::statement('ALTER TABLE product_variants ALTER COLUMN inventory_quantity SET DEFAULT 0');
        DB::statement('ALTER TABLE product_variants ALTER COLUMN inventory_quantity SET NOT NULL');
    }

    public function down(): void
    {
        // Tidak mengembalikan perubahan (aman untuk produksi)
        // Jika perlu, bisa di-ALTER kembali jadi NULLABLE & drop default.
    }
};
