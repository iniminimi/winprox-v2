<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('internal_teams', function (Blueprint $table) {
            $table->dropUnique(['field_qr_token']);
            $table->dropColumn('field_qr_token');
        });
    }

    public function down(): void
    {
        Schema::table('internal_teams', function (Blueprint $table) {
            $table->string('field_qr_token', 64)->nullable()->unique()->after('sort_order');
        });
    }
};
