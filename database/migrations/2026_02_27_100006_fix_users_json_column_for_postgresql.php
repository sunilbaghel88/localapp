<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // PostgreSQL: json type doesn't support equality, breaking SELECT DISTINCT.
        // Change to jsonb (has equality) or drop if unused.
        if (Schema::hasColumn('users', 'owner_permissions')) {
            $driver = Schema::getConnection()->getDriverName();
            if ($driver === 'pgsql') {
                DB::statement('ALTER TABLE users ALTER COLUMN owner_permissions TYPE jsonb USING owner_permissions::jsonb');
            }
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('users', 'owner_permissions')) {
            $driver = Schema::getConnection()->getDriverName();
            if ($driver === 'pgsql') {
                DB::statement('ALTER TABLE users ALTER COLUMN owner_permissions TYPE json USING owner_permissions::json');
            }
        }
    }
};
