<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('products', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('title');
            $table->string('handle')->nullable();
            $table->text('description')->nullable();
            $table->string('sku')->nullable();
            $table->decimal('price', 12, 2)->nullable();
            $table->decimal('compare_at_price', 12, 2)->nullable();
            $table->decimal('cost_price', 12, 2)->nullable();
            $table->integer('inventory_quantity')->default(0);
            $table->string('vendor')->nullable();
            $table->string('product_type')->nullable();
            $table->unsignedBigInteger('category_id')->nullable();
            $table->string('tags')->nullable();
            // Shipping/Tax/Weight
            $table->boolean('requires_shipping')->default(true);
            $table->boolean('taxable')->default(true);
            $table->decimal('weight', 10, 3)->nullable();
            $table->string('weight_unit')->nullable(); // g|kg|oz|lb
            // Options (schema + names)
            $table->json('options_schema')->nullable();
            $table->string('option1_name')->nullable();
            $table->string('option2_name')->nullable();
            $table->string('option3_name')->nullable();
            // Publishing & SEO
            $table->string('status')->default('draft'); // draft|active|archived
            $table->timestampTz('published_at')->nullable();
            $table->string('seo_title')->nullable();
            $table->string('seo_description')->nullable();
            // Sync to Shopify
            $table->unsignedBigInteger('shopify_product_id')->nullable();
            $table->string('sync_status')->nullable();
            $table->timestampTz('last_synced_at')->nullable();
            $table->text('last_error')->nullable();
            $table->timestampTz('shopify_updated_at')->nullable();

            $table->timestampsTz();
            $table->foreign('category_id')->references('id')->on('categories')->nullOnDelete();
        });
    }
    public function down(): void {
        Schema::dropIfExists('products');
    }
};
