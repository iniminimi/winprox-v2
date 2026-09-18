<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('clock_points', function (Blueprint $table) {
            $table->boolean('homescreen_shortcut')->default(false)->after('is_active');
        });
    }

    public function down(): void
    {
        Schema::table('clock_points', function (Blueprint $table) {
            $table->dropColumn('homescreen_shortcut');
        });
    }
};
