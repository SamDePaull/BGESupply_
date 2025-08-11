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
        Schema::create('shopify_products', function (Blueprint $table) {
            $table->unsignedBigInteger('id')->primary(); // Shopify product ID
            $table->string('title');
            $table->string('vendor')->nullable();
            $table->string('handle')->nullable();
            $table->text('body_html')->nullable();
            $table->string('status')->nullable(); // active, draft, archived
            $table->string('product_type')->nullable();
            $table->string('template_suffix')->nullable();
            $table->string('tags')->nullable(); // CSV string dari Shopify
            $table->timestampTz('published_at')->nullable();
            $table->string('admin_graphql_api_id')->nullable();

            $table->json('options')->nullable(); // array options (size, color, etc)
            $table->json('metafields')->nullable(); // kalau kamu mau menyimpan
            $table->string('image_url')->nullable(); // thumbnail utama (juga tersimpan detil di shopify_images)
            $table->json('extra')->nullable(); // ruang cadangan untuk field apapun di masa depan

            $table->timestampsTz();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('shopify_products');
    }
};
