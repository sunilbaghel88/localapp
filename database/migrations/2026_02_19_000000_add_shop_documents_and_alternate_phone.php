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
        Schema::table('shops', function (Blueprint $table) {
            $table->string('alternate_phone', 20)->nullable()->after('phone');
            $table->string('owner_photo')->nullable()->after('status');
            $table->string('aadhar_card')->nullable()->after('owner_photo');
            $table->string('shop_license')->nullable()->after('aadhar_card');
            $table->string('gst_certificate')->nullable()->after('shop_license');
            $table->string('electricity_bill')->nullable()->after('gst_certificate');
            $table->string('shop_front_photo')->nullable()->after('electricity_bill');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('shops', function (Blueprint $table) {
            $table->dropColumn([
                'alternate_phone',
                'owner_photo',
                'aadhar_card',
                'shop_license',
                'gst_certificate',
                'electricity_bill',
                'shop_front_photo',
            ]);
        });
    }
};
