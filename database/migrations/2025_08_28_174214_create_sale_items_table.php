<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('sale_items', function (Blueprint $table) {
            $table->bigIncrements('id');

            $table->foreignId('sale_id')->constrained('sales')->cascadeOnDelete();
            $table->foreignId('product_variant_id')->nullable()->constrained('product_variants')->nullOnDelete();

            // snapshot data, agar histori tetap terbaca meski produk berubah
            $table->string('sku')->nullable();
            $table->string('name');
            $table->decimal('price', 12, 2)->default(0);
            $table->decimal('cost_price', 12, 2)->nullable();
            $table->unsignedInteger('qty')->default(1);
            $table->decimal('line_total', 12, 2)->default(0);

            $table->timestampsTz();

            $table->index(['sale_id']);
            $table->index(['product_variant_id']);
            $table->index(['sku']);
        });
    }

    public function down(): void {
        Schema::dropIfExists('sale_items');
    }
};
