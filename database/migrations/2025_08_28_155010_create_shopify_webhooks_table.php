<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('shopify_webhooks', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('event_type')->nullable();
            $table->unsignedBigInteger('shopify_id')->nullable();
            $table->jsonb('payload')->nullable();
            $table->timestampsTz();
        });
    }
    public function down(): void {
        Schema::dropIfExists('shopify_webhooks');
    }
};
