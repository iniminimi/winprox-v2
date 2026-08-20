<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('promo_campaigns', function (Blueprint $table) {
            $table->timestamp('emails_paused_at')->nullable()->after('youtube_url');
        });
    }

    public function down(): void
    {
        Schema::table('promo_campaigns', function (Blueprint $table) {
            $table->dropColumn('emails_paused_at');
        });
    }
};
