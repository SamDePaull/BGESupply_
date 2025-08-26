<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('products', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('title')->nullable()->index();
            $table->longText('description')->nullable();
            $table->string('sku')->nullable();
            $table->decimal('price', 12, 2)->default(0);
            $table->integer('inventory_quantity')->default(0);
            $table->text('tags')->nullable();
            $table->string('vendor')->nullable();

            $table->unsignedBigInteger('shopify_product_id')->nullable()->unique();
            $table->string('sync_status')->default('pending')->index(); // pending|dirty|synced|failed
            $table->timestampTz('last_synced_at')->nullable();
            $table->text('last_error')->nullable();
            $table->timestampTz('shopify_updated_at')->nullable();

            $table->timestampsTz();
        });

        // Partial unique index on sku (PostgreSQL): only when not null and not empty
        DB::statement("CREATE UNIQUE INDEX products_sku_unique_partial ON products (sku) WHERE sku IS NOT NULL AND sku <> ''");
    }

    public function down(): void
    {
        // Drop partial index first
        try { DB::statement('DROP INDEX IF EXISTS products_sku_unique_partial'); } catch (\Throwable $e) {}
        Schema::dropIfExists('products');
    }
};
