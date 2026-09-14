<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->string('hsn_code', 16)->nullable()->after('status');
        });

        Schema::table('product_variants', function (Blueprint $table) {
            $table->decimal('cost_price', 12, 2)->unsigned()->nullable()->after('price');
        });

        Schema::create('purchase_invoices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('shop_id')->constrained()->cascadeOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('supplier_name')->nullable();
            $table->string('supplier_gstin', 15)->nullable();
            $table->string('invoice_number')->nullable();
            $table->date('invoice_date')->nullable();
            $table->decimal('cgst_amount', 12, 2)->unsigned()->default(0);
            $table->decimal('sgst_amount', 12, 2)->unsigned()->default(0);
            $table->decimal('igst_amount', 12, 2)->unsigned()->default(0);
            $table->string('source_filename')->nullable();
            $table->string('status')->default('imported');
            $table->timestamps();
        });

        Schema::create('purchase_invoice_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('purchase_invoice_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('product_variant_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name');
            $table->string('variant_name')->nullable();
            $table->string('hsn_code', 16)->nullable();
            $table->string('unit', 40)->nullable();
            $table->unsignedInteger('quantity')->default(0);
            $table->decimal('list_price', 12, 2)->unsigned()->default(0);
            $table->decimal('discount_percent', 6, 2)->unsigned()->default(0);
            $table->decimal('cost_price', 12, 2)->unsigned()->default(0);
            $table->decimal('selling_price', 12, 2)->unsigned()->default(0);
            $table->decimal('cgst_amount', 12, 2)->unsigned()->default(0);
            $table->decimal('sgst_amount', 12, 2)->unsigned()->default(0);
            $table->decimal('igst_amount', 12, 2)->unsigned()->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('purchase_invoice_items');
        Schema::dropIfExists('purchase_invoices');

        Schema::table('product_variants', function (Blueprint $table) {
            $table->dropColumn('cost_price');
        });

        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn('hsn_code');
        });
    }
};
