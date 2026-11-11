<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('tenants', 'photo_album_enabled')) {
            Schema::table('tenants', function (Blueprint $table): void {
                $table->boolean('photo_album_enabled')->default(false);
            });
        }

        if (! Schema::hasColumn('units', 'photo_album_enabled')) {
            return;
        }

        $tenantIds = DB::table('units')
            ->where('photo_album_enabled', true)
            ->distinct()
            ->pluck('tenant_id');

        if ($tenantIds->isNotEmpty()) {
            DB::table('tenants')->whereIn('id', $tenantIds)->update(['photo_album_enabled' => true]);
        }

        Schema::table('units', function (Blueprint $table): void {
            $table->dropColumn('photo_album_enabled');
        });
    }

    public function down(): void
    {
        if (! Schema::hasColumn('units', 'photo_album_enabled')) {
            Schema::table('units', function (Blueprint $table): void {
                $table->boolean('photo_album_enabled')->default(false);
            });
        }

        if (Schema::hasColumn('tenants', 'photo_album_enabled')) {
            Schema::table('tenants', function (Blueprint $table): void {
                $table->dropColumn('photo_album_enabled');
            });
        }
    }
};
