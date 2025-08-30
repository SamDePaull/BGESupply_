<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('collections', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('shopify_collection_id')->unique();
            $table->string('title');
            $table->string('handle')->nullable();
            $table->string('type')->nullable(); // smart|custom
            $table->longText('body_html')->nullable();
            $table->timestampTz('published_at')->nullable();
            $table->timestampsTz();
        });
    }
    public function down(): void {
        Schema::dropIfExists('collections');
    }
};
