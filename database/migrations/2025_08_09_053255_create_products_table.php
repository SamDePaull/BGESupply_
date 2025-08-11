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
        Schema::create('products', function (Blueprint $table) {
            $table->id();

            // bidang utama terpadu
            $table->string('name');
            $table->string('sku')->nullable()->index();
            $table->decimal('price', 12, 2)->default(0);
            $table->decimal('cost_price', 12, 2)->nullable();
            $table->integer('stock')->default(0);
            $table->string('image_url')->nullable();

            // mapping sumber
            $table->string('origin')->default('offline'); // offline|shopify
            $table->unsignedBigInteger('origin_id')->nullable()->index(); // offline_id atau shopify_product_id
            $table->unsignedBigInteger('shopify_product_id')->nullable()->index();
            $table->boolean('is_from_shopify')->default(false);
            $table->string('sync_status')->default('pending'); // pending|synced|failed

            $table->timestampsTz();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('products');
    }
};
