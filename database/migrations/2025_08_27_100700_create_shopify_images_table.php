<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('shopify_images', function (Blueprint $table) {
            $table->unsignedBigInteger('id')->primary();
            $table->unsignedBigInteger('shopify_product_id')->index();
            $table->integer('position')->nullable();
            $table->string('src')->nullable();
            $table->integer('width')->nullable();
            $table->integer('height')->nullable();
            $table->timestampTz('created_at_shopify')->nullable();
            $table->timestampTz('updated_at_shopify')->nullable();
            $table->json('variant_ids')->nullable();
            $table->timestampsTz();

            $table->foreign('shopify_product_id')->references('id')->on('shopify_products')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('shopify_images');
    }
};
