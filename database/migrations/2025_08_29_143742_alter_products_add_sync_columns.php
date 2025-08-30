<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::table('products', function (Blueprint $table) {
            if (!Schema::hasColumn('products','origin')) {
                $table->string('origin')->default('merged'); // merged|offline|shopify
            }
            if (!Schema::hasColumn('products','sync_enabled')) {
                $table->boolean('sync_enabled')->default(true);
            }
            if (!Schema::hasColumn('products','sync_channel')) {
                $table->string('sync_channel')->nullable(); // 'shopify','offline',etc.
            }
            if (!Schema::hasColumn('products','merged_from')) {
                $table->json('merged_from')->nullable(); // referensi staging row ids
            }
        });

        Schema::table('product_variants', function (Blueprint $table) {
            if (!Schema::hasColumn('product_variants','origin')) {
                $table->string('origin')->default('merged');
            }
            if (!Schema::hasColumn('product_variants','sync_enabled')) {
                $table->boolean('sync_enabled')->default(true);
            }
        });
    }
    public function down(): void {
        Schema::table('product_variants', function (Blueprint $table) {
            if (Schema::hasColumn('product_variants','origin')) $table->dropColumn('origin');
            if (Schema::hasColumn('product_variants','sync_enabled')) $table->dropColumn('sync_enabled');
        });
        Schema::table('products', function (Blueprint $table) {
            if (Schema::hasColumn('products','origin')) $table->dropColumn('origin');
            if (Schema::hasColumn('products','sync_enabled')) $table->dropColumn('sync_enabled');
            if (Schema::hasColumn('products','sync_channel')) $table->dropColumn('sync_channel');
            if (Schema::hasColumn('products','merged_from')) $table->dropColumn('merged_from');
        });
    }
};
