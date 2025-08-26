<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            if (!Schema::hasColumn('products', 'options_schema')) {
                $table->json('options_schema')->nullable()->after('tags');
            }
            if (!Schema::hasColumn('products', 'option1_name')) {
                $table->string('option1_name')->nullable()->after('options_schema');
            }
            if (!Schema::hasColumn('products', 'option2_name')) {
                $table->string('option2_name')->nullable()->after('option1_name');
            }
            if (!Schema::hasColumn('products', 'option3_name')) {
                $table->string('option3_name')->nullable()->after('option2_name');
            }
        });
        Schema::table('product_variants', function (Blueprint $table) {
            if (!Schema::hasColumn('product_variants', 'option1_value')) {
                $table->string('option1_value')->nullable()->after('title');
            }
            if (!Schema::hasColumn('product_variants', 'option2_value')) {
                $table->string('option2_value')->nullable()->after('option1_value');
            }
            if (!Schema::hasColumn('product_variants', 'option3_value')) {
                $table->string('option3_value')->nullable()->after('option2_value');
            }
            if (!Schema::hasColumn('product_variants', 'shopify_variant_id')) {
                $table->unsignedBigInteger('shopify_variant_id')->nullable()->unique()->after('inventory_quantity');
            }
            if (!Schema::hasColumn(
                'product_variants',
                'shopify_inventory_item_id'
            )) {
                $table->unsignedBigInteger('shopify_inventory_item_id')->nullable()->after('shopify_variant_id');
            }
        });
    }
    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            if (Schema::hasColumn('products', 'option3_name')) $table->dropColumn('option3_name');
            if (Schema::hasColumn('products', 'option2_name')) $table->dropColumn('option2_name');
            if (Schema::hasColumn('products', 'option1_name')) $table->dropColumn('option1_name');
            if (Schema::hasColumn('products', 'options_schema')) $table->dropColumn('options_schema');
        });
        Schema::table('product_variants', function (Blueprint $table) {
            if (Schema::hasColumn(
                'product_variants',
                'shopify_inventory_item_id'
            )) $table->dropColumn('shopify_inventory_item_id');
            if (Schema::hasColumn('product_variants', 'shopify_variant_id'))
                $table->dropColumn('shopify_variant_id');
            if (Schema::hasColumn('product_variants', 'option3_value')) $table->dropColumn('option3_value');
            if (Schema::hasColumn('product_variants', 'option2_value')) $table->dropColumn('option2_value');
            if (Schema::hasColumn('product_variants', 'option1_value')) $table->dropColumn('option1_value');
        });
    }
};
