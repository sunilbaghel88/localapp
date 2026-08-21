<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_user_type', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_type_id')->constrained()->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['user_id', 'user_type_id']);
        });

        if (Schema::hasColumn('users', 'user_type_id')) {
            $now = now();
            $inserts = [];

            foreach (DB::table('users')->whereNotNull('user_type_id')->select(['id', 'user_type_id'])->cursor() as $row) {
                $inserts[] = [
                    'user_id' => $row->id,
                    'user_type_id' => $row->user_type_id,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];

                if (count($inserts) >= 500) {
                    DB::table('user_user_type')->insert($inserts);
                    $inserts = [];
                }
            }

            if ($inserts !== []) {
                DB::table('user_user_type')->insert($inserts);
            }

            Schema::table('users', function (Blueprint $table) {
                $table->dropConstrainedForeignId('user_type_id');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('users') && ! Schema::hasColumn('users', 'user_type_id')) {
            Schema::table('users', function (Blueprint $table) {
                $table->foreignId('user_type_id')->nullable()->after('id')->constrained()->nullOnDelete();
            });

            $assignments = DB::table('user_user_type')
                ->orderBy('id')
                ->get(['user_id', 'user_type_id']);

            $firstByUser = [];
            foreach ($assignments as $row) {
                if (! isset($firstByUser[$row->user_id])) {
                    $firstByUser[$row->user_id] = $row->user_type_id;
                }
            }

            foreach ($firstByUser as $userId => $typeId) {
                DB::table('users')->where('id', $userId)->update(['user_type_id' => $typeId]);
            }
        }

        Schema::dropIfExists('user_user_type');
    }
};
