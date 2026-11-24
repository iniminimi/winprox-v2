<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('screens', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('unit_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('clock_point_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('role', 16);
            $table->string('display_id', 24)->nullable()->unique();
            $table->string('pairing_code', 8)->nullable();
            $table->timestamp('pairing_expires_at')->nullable();
            $table->string('device_hint', 64)->nullable();
            $table->timestamp('paired_at')->nullable();
            $table->timestamp('last_seen_at')->nullable();
            $table->timestamps();

            $table->unique('unit_id');
            $table->unique('clock_point_id');
            $table->index('pairing_code');
        });

        Schema::create('screen_claims', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('screen_id')->constrained()->cascadeOnDelete();
            $table->string('claim_token', 40)->unique();
            $table->string('pairing_code', 8);
            $table->string('device_hint', 64);
            $table->string('ip', 45)->nullable();
            $table->string('status', 16)->default('pending');
            $table->string('denied_reason', 32)->nullable();
            $table->text('issued_token')->nullable();
            $table->timestamp('expires_at');
            $table->timestamp('confirmed_at')->nullable();
            $table->unsignedBigInteger('pending_slot')->nullable();
            $table->timestamps();

            $table->unique('pending_slot');
            $table->index(['screen_id', 'status']);
        });

        $now = now();
        $linked = DB::table('clock_points')
            ->whereNotNull('display_paired_at')
            ->whereNotNull('display_secret')
            ->get(['id', 'tenant_id', 'display_id', 'display_device_hint', 'display_paired_at', 'display_last_seen_at']);

        foreach ($linked as $point) {
            DB::table('screens')->insert([
                'tenant_id' => $point->tenant_id,
                'clock_point_id' => $point->id,
                'role' => 'clock',
                'display_id' => $point->display_id,
                'device_hint' => $point->display_device_hint,
                'paired_at' => $point->display_paired_at,
                'last_seen_at' => $point->display_last_seen_at,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('screen_claims');
        Schema::dropIfExists('screens');
    }
};
