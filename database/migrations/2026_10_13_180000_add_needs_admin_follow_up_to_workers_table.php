<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('workers', function (Blueprint $table) {
            $table->boolean('needs_admin_follow_up')->default(false)->after('created_by_worker_id');
        });

        DB::table('workers')
            ->whereNotNull('created_by_worker_id')
            ->where('is_active', true)
            ->update(['needs_admin_follow_up' => true]);
    }

    public function down(): void
    {
        Schema::table('workers', function (Blueprint $table) {
            $table->dropColumn('needs_admin_follow_up');
        });
    }
};
