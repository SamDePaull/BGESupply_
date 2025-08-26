<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('shopify_products_raw', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('shopify_product_id')->unique()->index();
            $table->json('payload');
            $table->timestampTz('fetched_at')->nullable();
            $table->timestampsTz();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('shopify_products_raw');
    }
};
