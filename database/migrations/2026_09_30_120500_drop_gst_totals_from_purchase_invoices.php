<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('purchase_invoices', function (Blueprint $table) {
            $table->dropColumn(['cgst_amount', 'sgst_amount', 'igst_amount']);
        });
    }

    public function down(): void
    {
        Schema::table('purchase_invoices', function (Blueprint $table) {
            $table->decimal('cgst_amount', 12, 2)->unsigned()->default(0)->after('invoice_date');
            $table->decimal('sgst_amount', 12, 2)->unsigned()->default(0)->after('cgst_amount');
            $table->decimal('igst_amount', 12, 2)->unsigned()->default(0)->after('sgst_amount');
        });
    }
};
