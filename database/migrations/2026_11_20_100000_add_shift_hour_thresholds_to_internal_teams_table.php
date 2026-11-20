<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('internal_teams', function (Blueprint $table): void {
            $table->unsignedTinyInteger('stale_shift_hours')->default(16)->after('required_break_minutes');
            $table->unsignedTinyInteger('long_shift_hours')->default(10)->after('stale_shift_hours');
        });
    }

    public function down(): void
    {
        Schema::table('internal_teams', function (Blueprint $table): void {
            $table->dropColumn(['stale_shift_hours', 'long_shift_hours']);
        });
    }
};
