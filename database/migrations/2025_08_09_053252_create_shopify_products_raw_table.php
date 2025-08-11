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
        Schema::create('shopify_products_raw', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('shopify_product_id')->unique()->index();
            $table->json('payload'); // seluruh payload asli dari Shopify
            $table->timestampTz('fetched_at')->nullable(); // kapan diambil
            $table->timestampsTz();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('shopify_products_raw');
    }
};
