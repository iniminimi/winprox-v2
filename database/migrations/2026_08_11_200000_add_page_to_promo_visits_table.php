<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('promo_visits', function (Blueprint $table) {
            $table->string('page', 16)->default('promo')->after('locale');
            $table->index(['promo_recipient_id', 'page', 'visited_at']);
        });
    }

    public function down(): void
    {
        Schema::table('promo_visits', function (Blueprint $table) {
            $table->dropIndex(['promo_recipient_id', 'page', 'visited_at']);
            $table->dropColumn('page');
        });
    }
};
