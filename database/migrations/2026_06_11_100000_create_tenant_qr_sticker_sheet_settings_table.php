<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tenant_qr_sticker_sheet_settings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('template', 32);
            $table->string('header_text', 160)->nullable();
            $table->string('background_path')->nullable();
            $table->json('layout_config')->nullable();
            $table->timestamps();

            $table->unique(['tenant_id', 'template']);
        });

        if (Schema::hasColumn('tenants', 'qr_sticker_avery_62x89_header_text')) {
            $now = now();

            foreach (DB::table('tenants')
                ->whereNotNull('qr_sticker_avery_62x89_header_text')
                ->where('qr_sticker_avery_62x89_header_text', '!=', '')
                ->cursor() as $tenant) {
                DB::table('tenant_qr_sticker_sheet_settings')->insert([
                    'tenant_id' => $tenant->id,
                    'template' => 'avery_62x89_r',
                    'header_text' => $tenant->qr_sticker_avery_62x89_header_text,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }

            Schema::table('tenants', function (Blueprint $table) {
                $table->dropColumn('qr_sticker_avery_62x89_header_text');
            });
        }
    }

    public function down(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            if (! Schema::hasColumn('tenants', 'qr_sticker_avery_62x89_header_text')) {
                $table->string('qr_sticker_avery_62x89_header_text', 160)->nullable()->after('portal_background_path');
            }
        });

        foreach (DB::table('tenant_qr_sticker_sheet_settings')
            ->where('template', 'avery_62x89_r')
            ->whereNotNull('header_text')
            ->cursor() as $setting) {
            DB::table('tenants')
                ->where('id', $setting->tenant_id)
                ->update(['qr_sticker_avery_62x89_header_text' => $setting->header_text]);
        }

        Schema::dropIfExists('tenant_qr_sticker_sheet_settings');
    }
};
