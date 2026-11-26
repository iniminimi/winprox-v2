<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('tenants', 'translations_enabled')) {
            return;
        }

        Schema::table('tenants', function (Blueprint $table) {
            $table->boolean('translations_enabled')->default(false)->after('photo_album_enabled');
        });
    }

    public function down(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            $table->dropColumn('translations_enabled');
        });
    }
};
