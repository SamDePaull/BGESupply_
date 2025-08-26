<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('orders', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('source'); // offline|shopify
            $table->unsignedBigInteger('external_id')->nullable()->index(); // shopify order id
            $table->string('customer_name')->nullable();
            $table->decimal('total_price', 12, 2)->default(0);
            $table->json('raw_response')->nullable();
            $table->timestampsTz();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('orders');
    }
};
