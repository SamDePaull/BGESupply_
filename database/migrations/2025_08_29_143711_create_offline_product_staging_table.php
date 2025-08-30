<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('offline_product_staging', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->string('handle')->nullable();
            $table->string('sku')->nullable();
            $table->string('barcode')->nullable();
            $table->decimal('price', 12, 2)->nullable();
            $table->decimal('compare_at_price', 12, 2)->nullable();
            $table->integer('inventory_quantity')->nullable();
            $table->string('vendor')->nullable();
            $table->string('product_type')->nullable();
            $table->json('options')->nullable();   // [{name:'Color',values:['Red','Blue']}]
            $table->json('variants')->nullable();  // [{sku:'',option1:'',price:...,qty:...}]
            $table->json('images')->nullable();    // [{path:'storage/products/..',alt:'...'}]
            $table->string('status')->default('draft'); // draft|ready|merged|failed
            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }
    public function down(): void {
        Schema::dropIfExists('offline_product_staging');
    }
};
