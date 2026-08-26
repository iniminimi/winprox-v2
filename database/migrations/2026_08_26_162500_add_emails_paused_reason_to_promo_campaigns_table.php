<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('promo_campaigns', function (Blueprint $table) {
            $table->string('emails_paused_reason', 32)->nullable()->after('emails_paused_at');
            $table->string('emails_paused_detail', 500)->nullable()->after('emails_paused_reason');
        });
    }

    public function down(): void
    {
        Schema::table('promo_campaigns', function (Blueprint $table) {
            $table->dropColumn(['emails_paused_reason', 'emails_paused_detail']);
        });
    }
};
