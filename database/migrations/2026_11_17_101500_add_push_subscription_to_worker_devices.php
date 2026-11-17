<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('worker_devices', function (Blueprint $table) {
            $table->string('push_endpoint', 1024)->nullable()->after('last_seen_at');
            $table->char('push_endpoint_hash', 64)->nullable()->unique()->after('push_endpoint');
            $table->string('push_public_key', 255)->nullable()->after('push_endpoint_hash');
            $table->string('push_auth_token', 255)->nullable()->after('push_public_key');
            $table->string('push_locale', 5)->nullable()->after('push_auth_token');
        });
    }

    public function down(): void
    {
        Schema::table('worker_devices', function (Blueprint $table) {
            $table->dropUnique(['push_endpoint_hash']);
            $table->dropColumn([
                'push_endpoint',
                'push_endpoint_hash',
                'push_public_key',
                'push_auth_token',
                'push_locale',
            ]);
        });
    }
};
