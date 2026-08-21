<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('shop_type_user_type', function (Blueprint $table) {
            $table->id();
            $table->foreignId('shop_type_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_type_id')->constrained()->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['shop_type_id', 'user_type_id']);
        });

        if (! Schema::hasColumn('shop_types', 'supports_partner_rewards')) {
            Schema::table('shop_types', function (Blueprint $table) {
                $table->boolean('supports_partner_rewards')->default(false)->after('sort_order');
            });
        }

        if (Schema::hasColumn('shop_types', 'supports_electrician_rewards')) {
            DB::table('shop_types')->update([
                'supports_partner_rewards' => DB::raw('supports_electrician_rewards'),
            ]);
        }

        if (Schema::hasColumn('shop_types', 'electrician_user_type_id')) {
            $now = now();
            $inserts = [];

            foreach (DB::table('shop_types')
                ->whereNotNull('electrician_user_type_id')
                ->select(['id', 'electrician_user_type_id'])
                ->cursor() as $row) {
                $inserts[] = [
                    'shop_type_id' => $row->id,
                    'user_type_id' => $row->electrician_user_type_id,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];

                if (count($inserts) >= 500) {
                    DB::table('shop_type_user_type')->insert($inserts);
                    $inserts = [];
                }
            }

            if ($inserts !== []) {
                DB::table('shop_type_user_type')->insert($inserts);
            }

            Schema::table('shop_types', function (Blueprint $table) {
                $table->dropConstrainedForeignId('electrician_user_type_id');
            });
        }

        if (Schema::hasColumn('shop_types', 'supports_electrician_rewards')) {
            Schema::table('shop_types', function (Blueprint $table) {
                $table->dropColumn('supports_electrician_rewards');
            });
        }
    }

    public function down(): void
    {
        if (! Schema::hasColumn('shop_types', 'supports_electrician_rewards')) {
            Schema::table('shop_types', function (Blueprint $table) {
                $table->boolean('supports_electrician_rewards')->default(false)->after('sort_order');
            });
        }

        if (Schema::hasColumn('shop_types', 'supports_partner_rewards')) {
            DB::table('shop_types')->update([
                'supports_electrician_rewards' => DB::raw('supports_partner_rewards'),
            ]);
        }

        if (! Schema::hasColumn('shop_types', 'electrician_user_type_id')) {
            Schema::table('shop_types', function (Blueprint $table) {
                $table->foreignId('electrician_user_type_id')
                    ->nullable()
                    ->after('supports_electrician_rewards')
                    ->constrained('user_types')
                    ->nullOnDelete();
            });

            $firstByShopType = [];
            foreach (DB::table('shop_type_user_type')->orderBy('id')->get() as $row) {
                if (! isset($firstByShopType[$row->shop_type_id])) {
                    $firstByShopType[$row->shop_type_id] = $row->user_type_id;
                }
            }

            foreach ($firstByShopType as $shopTypeId => $userTypeId) {
                DB::table('shop_types')->where('id', $shopTypeId)->update([
                    'electrician_user_type_id' => $userTypeId,
                ]);
            }
        }

        if (Schema::hasColumn('shop_types', 'supports_partner_rewards')) {
            Schema::table('shop_types', function (Blueprint $table) {
                $table->dropColumn('supports_partner_rewards');
            });
        }

        Schema::dropIfExists('shop_type_user_type');
    }
};
