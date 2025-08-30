<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('product_images', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('product_id');
            $table->string('file_path');
            $table->string('alt')->nullable();
            $table->integer('position')->nullable();
            $table->boolean('is_primary')->default(false);
            $table->unsignedBigInteger('shopify_image_id')->nullable();
            $table->timestampsTz();

            $table->foreign('product_id')->references('id')->on('products')->cascadeOnDelete();
        });
    }
    public function down(): void {
        Schema::dropIfExists('product_images');
    }
};
