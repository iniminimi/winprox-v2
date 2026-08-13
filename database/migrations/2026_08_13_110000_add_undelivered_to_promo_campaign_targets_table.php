<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('promo_campaign_targets', function (Blueprint $table) {
            $table->boolean('undelivered')->default(false)->after('generated_at');
            $table->index(['promo_campaign_id', 'undelivered']);
        });
    }

    public function down(): void
    {
        Schema::table('promo_campaign_targets', function (Blueprint $table) {
            $table->dropIndex(['promo_campaign_id', 'undelivered']);
            $table->dropColumn('undelivered');
        });
    }
};
