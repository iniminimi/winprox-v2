<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('workers', function (Blueprint $table) {
            $table->unsignedBigInteger('last_team_document_id_seen')->nullable()->after('clock_device_id');
            $table->unsignedBigInteger('last_team_announcement_id_seen')->nullable()->after('last_team_document_id_seen');
        });
    }

    public function down(): void
    {
        Schema::table('workers', function (Blueprint $table) {
            $table->dropColumn([
                'last_team_document_id_seen',
                'last_team_announcement_id_seen',
            ]);
        });
    }
};
