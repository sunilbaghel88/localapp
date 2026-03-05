<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('shop_types', function (Blueprint $table) {
            $table->boolean('supports_electrician_rewards')->default(false)->after('sort_order');
            $table->foreignId('electrician_user_type_id')->nullable()->after('supports_electrician_rewards')->constrained('user_types')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('shop_types', function (Blueprint $table) {
            $table->dropForeign(['electrician_user_type_id']);
            $table->dropColumn(['supports_electrician_rewards', 'electrician_user_type_id']);
        });
    }
};
