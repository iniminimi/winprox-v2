<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('tenants', 'time_roster_qr_token')) {
            return;
        }

        Schema::table('tenants', function (Blueprint $table) {
            $table->dropUnique(['time_roster_qr_token']);
            $table->dropColumn('time_roster_qr_token');
        });
    }

    public function down(): void
    {
        if (Schema::hasColumn('tenants', 'time_roster_qr_token')) {
            return;
        }

        Schema::table('tenants', function (Blueprint $table) {
            $table->string('time_roster_qr_token', 64)->nullable()->unique()->after('has_time_module');
        });
    }
};
