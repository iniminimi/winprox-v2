<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('clock_points', 'display_id')) {
            Schema::table('clock_points', function (Blueprint $table) {
                // TFT-klokscherm (ESP32): stabiele publieke sleutel + HMAC-secret
                // voor roterende QR + wpclk_-device-token voor pings.
                $table->string('display_id', 24)->nullable()->unique()->after('homescreen_shortcut');
                $table->text('display_secret')->nullable()->after('display_id');
                $table->string('display_token_hash', 64)->nullable()->after('display_secret');
                $table->string('display_token_prefix', 12)->nullable()->after('display_token_hash');
                $table->string('display_pairing_code', 8)->nullable()->after('display_token_prefix');
                $table->timestamp('display_pairing_expires_at')->nullable()->after('display_pairing_code');
                $table->string('display_device_hint', 64)->nullable()->after('display_pairing_expires_at');
                $table->timestamp('display_paired_at')->nullable()->after('display_device_hint');
                $table->timestamp('display_last_seen_at')->nullable()->after('display_paired_at');

                $table->index('display_pairing_code');
            });
        }

        Schema::create('clock_display_claims', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('clock_point_id')->constrained()->cascadeOnDelete();
            // Ongokbare poll-handle voor het device (claim-status endpoint).
            $table->string('claim_token', 40)->unique();
            $table->string('pairing_code', 8);
            $table->string('device_hint', 64);
            $table->string('ip', 45)->nullable();
            $table->string('status', 16)->default('pending');
            $table->string('denied_reason', 32)->nullable();
            // Encrypted plaintext wpclk_-token: claim-status geeft het terug aan
            // het device na confirm (device bewaart het in NVS; hash zelf is
            // niet omkeerbaar dus plaintext hoort alleen hier).
            $table->text('issued_token')->nullable();
            $table->timestamp('expires_at');
            $table->timestamp('confirmed_at')->nullable();
            // Structurele single-pending-per-punt: clock_point_id zolang
            // status='pending', anders NULL (unique laat meerdere NULLs toe).
            // Gewone kolom — MySQL staat geen FK op een kolom toe die een
            // generated-column gebruikt; het model synct deze bij elke save.
            $table->unsignedBigInteger('pending_slot')->nullable();
            $table->timestamps();

            $table->unique('pending_slot');
            $table->index(['clock_point_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('clock_display_claims');

        Schema::table('clock_points', function (Blueprint $table) {
            $table->dropIndex(['display_pairing_code']);
            $table->dropColumn([
                'display_id',
                'display_secret',
                'display_token_hash',
                'display_token_prefix',
                'display_pairing_code',
                'display_pairing_expires_at',
                'display_device_hint',
                'display_paired_at',
                'display_last_seen_at',
            ]);
        });
    }
};
