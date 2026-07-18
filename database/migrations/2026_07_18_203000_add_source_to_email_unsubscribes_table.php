<?php

declare(strict_types=1);

use App\Enums\EmailUnsubscribeSource;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('email_unsubscribes', function (Blueprint $table) {
            $table->string('source', 32)
                ->default(EmailUnsubscribeSource::Voluntary->value)
                ->after('email');
        });

        // Bounce automation started around mid-July 2026; treat those as undeliverable.
        DB::table('email_unsubscribes')
            ->where('unsubscribed_at', '>=', '2026-07-17 00:00:00')
            ->update(['source' => EmailUnsubscribeSource::Undeliverable->value]);
    }

    public function down(): void
    {
        Schema::table('email_unsubscribes', function (Blueprint $table) {
            $table->dropColumn('source');
        });
    }
};
