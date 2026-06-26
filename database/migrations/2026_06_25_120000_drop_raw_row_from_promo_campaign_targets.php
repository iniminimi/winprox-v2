<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('promo_campaign_targets', 'raw_row')) {
            return;
        }

        Schema::table('promo_campaign_targets', function (Blueprint $table) {
            $table->dropColumn('raw_row');
        });
    }

    public function down(): void
    {
        Schema::table('promo_campaign_targets', function (Blueprint $table) {
            $table->json('raw_row')->after('promo_recipient_id');
        });
    }
};
