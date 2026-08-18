<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            $table->string('starter_pack_key', 32)->nullable()->after('has_time_module');
            $table->timestamp('starter_pack_applied_at')->nullable()->after('starter_pack_key');
            $table->json('starter_pack_payload')->nullable()->after('starter_pack_applied_at');
        });
    }

    public function down(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            $table->dropColumn([
                'starter_pack_key',
                'starter_pack_applied_at',
                'starter_pack_payload',
            ]);
        });
    }
};
