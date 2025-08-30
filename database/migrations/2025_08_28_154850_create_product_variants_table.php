<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void {
        Schema::create('product_variants', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('product_id');
            $table->string('title')->nullable();
            $table->string('option1_value')->nullable();
            $table->string('option2_value')->nullable();
            $table->string('option3_value')->nullable();
            $table->string('sku')->nullable();
            $table->string('barcode')->nullable();
            $table->decimal('price', 12, 2)->nullable();
            $table->decimal('compare_at_price', 12, 2)->nullable();
            $table->integer('inventory_quantity')->default(0);
            $table->boolean('requires_shipping')->default(true);
            $table->boolean('taxable')->default(true);
            $table->decimal('weight', 10, 3)->nullable();
            $table->string('weight_unit')->nullable();
            $table->unsignedBigInteger('shopify_variant_id')->nullable();
            $table->unsignedBigInteger('shopify_inventory_item_id')->nullable();
            $table->unsignedBigInteger('product_image_id')->nullable();
            $table->timestampsTz();

            $table->foreign('product_id')->references('id')->on('products')->cascadeOnDelete();
        });
        // Unik per kombinasi opsi
        DB::statement("CREATE UNIQUE INDEX IF NOT EXISTS ux_variants_combo ON product_variants (product_id, COALESCE(option1_value, ''), COALESCE(option2_value, ''), COALESCE(option3_value, ''))");
        DB::statement('CREATE INDEX IF NOT EXISTS ix_variants_sku ON product_variants (sku)');
    }
    public function down(): void {
        Schema::dropIfExists('product_variants');
    }
};
