<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('sync_logs', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('job')->index();           // PushProductToShopify / UpdateProductOnShopify / DeleteProductOnShopify
            $table->string('action')->index();        // create|update|delete|pull
            $table->unsignedBigInteger('product_id')->nullable()->index();
            $table->unsignedBigInteger('shopify_product_id')->nullable()->index();
            $table->integer('http_status')->nullable();
            $table->string('status')->index();        // ok|failed
            $table->string('message')->nullable();    // ringkas error/sukses
            $table->json('context')->nullable();      // potongan payload / body
            $table->timestampsTz();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sync_logs');
    }
};
