<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Normalize shop status to 'on' | 'off'. Only shops with status 'on' have products visible on the Dashboard.
     */
    public function up(): void
    {
        DB::table('shops')->where('status', 'active')->update(['status' => 'on']);
        DB::table('shops')->whereNotIn('status', ['on'])->update(['status' => 'off']);
    }

    public function down(): void
    {
        DB::table('shops')->where('status', 'on')->update(['status' => 'active']);
        DB::table('shops')->where('status', 'off')->update(['status' => 'inactive']);
    }
};
