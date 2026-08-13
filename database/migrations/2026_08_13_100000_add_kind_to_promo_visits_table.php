<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('promo_visits', function (Blueprint $table) {
            $table->string('kind', 16)->default('hit')->after('page');
            $table->string('follow_key', 32)->nullable()->after('kind');
            $table->index(['promo_recipient_id', 'kind', 'visited_at']);
        });
    }

    public function down(): void
    {
        Schema::table('promo_visits', function (Blueprint $table) {
            $table->dropIndex(['promo_recipient_id', 'kind', 'visited_at']);
            $table->dropColumn(['kind', 'follow_key']);
        });
    }
};
