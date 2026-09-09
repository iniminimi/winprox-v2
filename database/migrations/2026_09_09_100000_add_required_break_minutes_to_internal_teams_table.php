<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('internal_teams', function (Blueprint $table) {
            $table->unsignedSmallInteger('required_break_minutes')->nullable()->after('clocks_all_locations');
        });
    }

    public function down(): void
    {
        Schema::table('internal_teams', function (Blueprint $table) {
            $table->dropColumn('required_break_minutes');
        });
    }
};
