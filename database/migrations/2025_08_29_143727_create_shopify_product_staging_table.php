<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('shopify_product_staging', function (Blueprint $table) {
            $table->id();
            $table->bigInteger('shopify_product_id')->index();
            $table->string('handle')->nullable();
            $table->string('title')->nullable();
            $table->json('payload'); // simpan JSON penuh dari Shopify (product + variants + images)
            $table->string('status')->default('pulled'); // pulled|ready|merged|failed
            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }
    public function down(): void {
        Schema::dropIfExists('shopify_product_staging');
    }
};
