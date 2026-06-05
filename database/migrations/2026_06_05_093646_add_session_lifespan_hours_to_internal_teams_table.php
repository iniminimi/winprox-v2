<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('internal_teams', function (Blueprint $table) {
            $table->integer('session_lifespan_hours')->nullable()->after('field_qr_token');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('internal_teams', function (Blueprint $table) {
            $table->dropColumn('session_lifespan_hours');
        });
    }
};
