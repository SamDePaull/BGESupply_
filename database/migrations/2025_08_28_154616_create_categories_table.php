<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('categories', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('name')->unique();
            $table->string('shopify_category')->nullable();
            $table->timestampsTz();
        });
    }
    public function down(): void {
        Schema::dropIfExists('categories');
    }
};
