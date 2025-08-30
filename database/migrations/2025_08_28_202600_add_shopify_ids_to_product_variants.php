<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('product_variants', function (Blueprint $table) {
            if (!Schema::hasColumn('product_variants', 'shopify_variant_id')) {
                $table->bigInteger('shopify_variant_id')->nullable()->index();
            }
            if (!Schema::hasColumn('product_variants', 'shopify_inventory_item_id')) {
                $table->bigInteger('shopify_inventory_item_id')->nullable()->index();
            }
        });
    }
    public function down(): void
    {
        Schema::table('product_variants', function (Blueprint $table) {
            if (Schema::hasColumn('product_variants', 'shopify_variant_id')) {
                $table->dropColumn('shopify_variant_id');
            }
            if (Schema::hasColumn('product_variants', 'shopify_inventory_item_id')) {
                $table->dropColumn('shopify_inventory_item_id');
            }
        });
    }
};
