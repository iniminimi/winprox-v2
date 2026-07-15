<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('promo_campaigns', function (Blueprint $table) {
            $table->boolean('attach_letter_to_email')->default(true)->after('email_body_html');
        });
    }

    public function down(): void
    {
        Schema::table('promo_campaigns', function (Blueprint $table) {
            $table->dropColumn('attach_letter_to_email');
        });
    }
};
