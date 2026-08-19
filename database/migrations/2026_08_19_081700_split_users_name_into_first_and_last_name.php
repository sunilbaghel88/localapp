<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('first_name')->default('')->after('id');
            $table->string('last_name')->default('')->after('first_name');
        });

        DB::table('users')->orderBy('id')->each(function ($user) {
            $full = trim((string) ($user->name ?? ''));
            $parts = $full === '' ? ['', ''] : (preg_split('/\s+/', $full, 2) ?: ['', '']);

            DB::table('users')->where('id', $user->id)->update([
                'first_name' => $parts[0] ?? '',
                'last_name' => $parts[1] ?? '',
            ]);
        });

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('name');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('name')->default('')->after('id');
        });

        DB::table('users')->orderBy('id')->each(function ($user) {
            $name = trim(($user->first_name ?? '').' '.($user->last_name ?? ''));

            DB::table('users')->where('id', $user->id)->update([
                'name' => $name,
            ]);
        });

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['first_name', 'last_name']);
        });
    }
};
