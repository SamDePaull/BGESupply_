<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('sales', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('number')->unique(); // misal: POS-20250829-0001
            $table->foreignId('cashier_user_id')->nullable()->constrained('users')->nullOnDelete();

            $table->string('customer_name')->nullable();

            $table->decimal('subtotal', 12, 2)->default(0);
            $table->decimal('discount', 12, 2)->default(0);
            $table->decimal('tax', 12, 2)->default(0);
            $table->decimal('total', 12, 2)->default(0);

            $table->decimal('paid_amount', 12, 2)->default(0);
            $table->decimal('change_amount', 12, 2)->default(0);

            $table->string('payment_method', 20)->default('cash'); // cash|card|qris|transfer|...
            $table->string('status', 20)->default('paid'); // paid|draft|void

            $table->timestampsTz();
        });
    }

    public function down(): void {
        Schema::dropIfExists('sales');
    }
};
