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
Schema::create('shopify_images', function (Blueprint $table) {
            $table->unsignedBigInteger('id')->primary(); // Shopify image ID
            $table->unsignedBigInteger('shopify_product_id')->index();
            $table->integer('position')->nullable();
            $table->string('src')->nullable();
            $table->integer('width')->nullable();
            $table->integer('height')->nullable();
            $table->string('alt')->nullable();
            $table->string('admin_graphql_api_id')->nullable();

            // 👉 rename Shopify timestamps
            $table->timestampTz('shopify_created_at')->nullable();
            $table->timestampTz('shopify_updated_at')->nullable();

            $table->json('variant_ids')->nullable();
            $table->json('extra')->nullable();

            // Laravel row timestamps
            $table->timestampsTz();

            $table->foreign('shopify_product_id')->references('id')->on('shopify_products')->cascadeOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('shopify_images');
    }
};
