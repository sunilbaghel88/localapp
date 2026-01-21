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
        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('shop_id')->constrained()->cascadeOnDelete();
            $table->foreignId('address_id')->nullable()->constrained()->nullOnDelete();
            $table->string('status')->default('pending'); // pending, paid, processing, shipped, delivered, cancelled, refunded
            $table->string('payment_status')->default('unpaid'); // unpaid, paid, failed, refunded
            $table->decimal('subtotal', 10, 2)->unsigned();
            $table->decimal('discount_total', 10, 2)->unsigned()->default(0);
            $table->decimal('shipping_total', 10, 2)->unsigned()->default(0);
            $table->decimal('tax_total', 10, 2)->unsigned()->default(0);
            $table->decimal('grand_total', 10, 2)->unsigned();
            $table->json('meta')->nullable(); // e.g. delivery notes
            $table->timestamps();
        });

        Schema::create('order_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_variant_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name');
            $table->unsignedInteger('quantity');
            $table->decimal('price', 10, 2)->unsigned(); // unit price
            $table->decimal('discount', 10, 2)->unsigned()->default(0);
            $table->decimal('total', 10, 2)->unsigned(); // quantity * price - discount
            $table->json('attributes')->nullable(); // snapshot of variant attributes
            $table->timestamps();
        });

        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->string('provider')->nullable(); // e.g. stripe, razorpay
            $table->string('provider_payment_id')->nullable();
            $table->decimal('amount', 10, 2)->unsigned();
            $table->string('status')->default('initiated'); // initiated, succeeded, failed, refunded
            $table->json('meta')->nullable(); // raw payload/response
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('payments');
        Schema::dropIfExists('order_items');
        Schema::dropIfExists('orders');
    }
};
