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
        Schema::table('tenants', function (Blueprint $table) {
            $table->boolean('custom_theme_active')->default(false)->after('logo_path');
            $table->string('custom_theme_bg', 7)->nullable()->after('custom_theme_active');
            $table->string('custom_theme_btn', 7)->nullable()->after('custom_theme_bg');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            $table->dropColumn(['custom_theme_active', 'custom_theme_bg', 'custom_theme_btn']);
        });
    }
};
