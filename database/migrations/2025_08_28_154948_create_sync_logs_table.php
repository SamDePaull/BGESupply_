<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('sync_logs', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('job')->nullable();
            $table->string('action')->nullable();
            $table->unsignedBigInteger('product_id')->nullable();
            $table->unsignedBigInteger('variant_id')->nullable();
            $table->unsignedBigInteger('shopify_product_id')->nullable();
            $table->unsignedBigInteger('shopify_variant_id')->nullable();
            $table->integer('http_status')->nullable();
            $table->string('status')->nullable(); // ok|failed|pending
            $table->string('message')->nullable();
            $table->jsonb('body')->nullable();
            $table->timestampsTz();
        });
    }
    public function down(): void {
        Schema::dropIfExists('sync_logs');
    }
};
