<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('shopify_webhooks', function (Blueprint $table) {
            $table->id();
            $table->string('event_type'); // products/update, orders/create, dll
            $table->unsignedBigInteger('shopify_id')->nullable(); // product/order id
            $table->json('payload');
            $table->timestampsTz();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('shopify_webhooks');
    }
};
