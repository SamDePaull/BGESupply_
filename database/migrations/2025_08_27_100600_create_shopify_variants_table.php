<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('shopify_variants', function (Blueprint $table) {
            $table->unsignedBigInteger('id')->primary();
            $table->unsignedBigInteger('shopify_product_id')->index();
            $table->string('title')->nullable();
            $table->decimal('price', 12, 2)->nullable();
            $table->integer('position')->nullable();
            $table->string('inventory_policy')->nullable();
            $table->decimal('compare_at_price', 12, 2)->nullable();
            $table->string('option1')->nullable();
            $table->string('option2')->nullable();
            $table->string('option3')->nullable();
            $table->string('sku')->nullable()->index();
            $table->string('barcode')->nullable();
            $table->unsignedBigInteger('image_id')->nullable();
            $table->unsignedBigInteger('inventory_item_id')->nullable()->index();
            $table->integer('inventory_quantity')->nullable();
            $table->decimal('weight', 8, 3)->nullable();
            $table->integer('grams')->nullable();
            $table->string('weight_unit')->nullable();
            $table->boolean('taxable')->nullable();
            $table->string('tax_code')->nullable();
            $table->boolean('requires_shipping')->nullable();
            $table->integer('old_inventory_quantity')->nullable();
            $table->timestampTz('created_at_shopify')->nullable();
            $table->timestampTz('updated_at_shopify')->nullable();
            $table->timestampsTz();

            $table->foreign('shopify_product_id')->references('id')->on('shopify_products')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('shopify_variants');
    }
};
