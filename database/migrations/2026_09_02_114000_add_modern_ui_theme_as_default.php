<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('users')
            ->whereIn('ui_theme', ['simple', 'highres'])
            ->update(['ui_theme' => 'modern']);

        if (Schema::getConnection()->getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE users MODIFY ui_theme VARCHAR(16) NOT NULL DEFAULT 'modern'");
        }
    }

    public function down(): void
    {
        DB::table('users')
            ->where('ui_theme', 'modern')
            ->update(['ui_theme' => 'simple']);

        if (Schema::getConnection()->getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE users MODIFY ui_theme VARCHAR(16) NOT NULL DEFAULT 'simple'");
        }
    }
};
