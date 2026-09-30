<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('offline_bills', function (Blueprint $table) {
            $table->string('payment_mode')->nullable()->after('type');
        });
    }

    public function down(): void
    {
        Schema::table('offline_bills', function (Blueprint $table) {
            $table->dropColumn('payment_mode');
        });
    }
};
