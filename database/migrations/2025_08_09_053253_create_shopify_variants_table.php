<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
                Schema::create('shopify_variants', function (Blueprint $table) {
            $table->unsignedBigInteger('id')->primary(); // Shopify variant ID
            $table->unsignedBigInteger('shopify_product_id')->index();

            $table->string('title')->nullable();
            $table->decimal('price', 12, 2)->nullable();
            $table->integer('position')->nullable();
            $table->string('inventory_policy')->nullable(); // deny/continue
            $table->decimal('compare_at_price', 12, 2)->nullable();
            $table->string('option1')->nullable();
            $table->string('option2')->nullable();
            $table->string('option3')->nullable();

            // 👉 rename Shopify timestamps to avoid collision with Laravel timestamps
            $table->timestampTz('shopify_created_at')->nullable();
            $table->timestampTz('shopify_updated_at')->nullable();

            $table->boolean('taxable')->default(true);
            $table->string('barcode')->nullable();
            $table->string('fulfillment_service')->nullable();
            $table->integer('grams')->nullable();
            $table->string('inventory_management')->nullable(); // shopify/null
            $table->boolean('requires_shipping')->default(true);
            $table->string('sku')->nullable();
            $table->decimal('weight', 8, 2)->nullable();
            $table->string('weight_unit', 10)->nullable();

            $table->unsignedBigInteger('inventory_item_id')->nullable();
            $table->integer('inventory_quantity')->default(0);
            $table->integer('old_inventory_quantity')->nullable();
            $table->string('admin_graphql_api_id')->nullable();
            $table->unsignedBigInteger('image_id')->nullable();

            $table->json('extra')->nullable();

            // Laravel timestamps (DB row bookkeeping)
            $table->timestampsTz();

            // You can add a foreign key if you want strict referential integrity:
            $table->foreign('shopify_product_id')->references('id')->on('shopify_products')->cascadeOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('shopify_variants');
    }
};
