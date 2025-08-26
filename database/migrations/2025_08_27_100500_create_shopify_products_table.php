<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('shopify_products', function (Blueprint $table) {
            $table->unsignedBigInteger('id')->primary();
            $table->string('title')->nullable();
            $table->string('vendor')->nullable();
            $table->string('product_type')->nullable();
            $table->string('status')->nullable(); // active, draft, archived
            $table->string('handle')->nullable();
            $table->timestampTz('created_at_shopify')->nullable();
            $table->timestampTz('updated_at_shopify')->nullable();
            $table->timestampTz('published_at_shopify')->nullable();
            $table->string('tags')->nullable();
            $table->json('options')->nullable();
            $table->longText('body_html')->nullable();
            $table->json('extra')->nullable();
            $table->timestampsTz();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('shopify_products');
    }
};
