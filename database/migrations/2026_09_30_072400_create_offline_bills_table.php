<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('offline_bills', function (Blueprint $table) {
            $table->id();
            $table->foreignId('shop_id')->constrained()->cascadeOnDelete();
            $table->foreignId('created_by')->constrained('users')->cascadeOnDelete();
            $table->string('type')->default('debit');
            $table->foreignId('customer_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('partner_id')->nullable()->constrained('users')->nullOnDelete();
            $table->unsignedInteger('reward_points')->default(0);
            $table->decimal('amount', 12, 2);
            $table->text('remarks')->nullable();
            $table->string('image_path')->nullable();
            $table->timestamps();
        });

        Schema::table('user_reward_grants', function (Blueprint $table) {
            $table->foreignId('offline_bill_id')->nullable()->after('order_id')->constrained('offline_bills')->nullOnDelete();
        });

        $driver = Schema::getConnection()->getDriverName();
        if ($driver === 'pgsql') {
            DB::statement('ALTER TABLE user_reward_grants ALTER COLUMN order_id DROP NOT NULL');
        } else {
            Schema::table('user_reward_grants', function (Blueprint $table) {
                $table->unsignedBigInteger('order_id')->nullable()->change();
            });
        }
    }

    public function down(): void
    {
        Schema::table('user_reward_grants', function (Blueprint $table) {
            $table->dropConstrainedForeignId('offline_bill_id');
        });

        $driver = Schema::getConnection()->getDriverName();
        if ($driver === 'pgsql') {
            DB::statement('ALTER TABLE user_reward_grants ALTER COLUMN order_id SET NOT NULL');
        } else {
            Schema::table('user_reward_grants', function (Blueprint $table) {
                $table->unsignedBigInteger('order_id')->nullable(false)->change();
            });
        }

        Schema::dropIfExists('offline_bills');
    }
};
