<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_variants', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->foreignId('product_id')->constrained('products')->cascadeOnDelete();
            $table->string('title')->nullable();
            $table->string('sku')->nullable();
            $table->decimal('price', 12, 2)->nullable();
            $table->integer('inventory_quantity')->default(0);
            $table->unsignedBigInteger('shopify_variant_id')->nullable()->unique();
            $table->unsignedBigInteger('shopify_inventory_item_id')->nullable();
            $table->json('options')->nullable(); // color/size etc
            $table->timestampsTz();
        });

        DB::statement("CREATE UNIQUE INDEX product_variants_sku_unique_partial ON product_variants (sku) WHERE sku IS NOT NULL AND sku <> ''");
    }

    public function down(): void
    {
        try { DB::statement('DROP INDEX IF EXISTS product_variants_sku_unique_partial'); } catch (\Throwable $e) {}
        Schema::dropIfExists('product_variants');
    }
};
