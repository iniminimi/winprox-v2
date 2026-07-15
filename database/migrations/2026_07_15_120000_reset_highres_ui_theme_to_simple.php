<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('users')
            ->where('ui_theme', 'highres')
            ->update(['ui_theme' => 'simple']);
    }

    public function down(): void
    {
        // High-res theme removed; no rollback.
    }
};
