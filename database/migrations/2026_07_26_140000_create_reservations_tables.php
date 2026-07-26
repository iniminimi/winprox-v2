<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('categories', function (Blueprint $table) {
            $table->boolean('is_reservable')->default(false)->after('allow_gps_location');
        });

        Schema::table('units', function (Blueprint $table) {
            $table->boolean('allow_reservations')->default(true)->after('public_reports_enabled');
        });

        Schema::create('reservations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('unit_id')->constrained()->cascadeOnDelete();
            $table->foreignId('worker_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('guest_first_name', 100);
            $table->string('guest_last_name', 100);
            $table->string('guest_email', 255);
            $table->dateTime('start_at');
            $table->dateTime('end_at');
            $table->dateTime('expires_at')->nullable();
            $table->dateTime('confirmed_at')->nullable();
            $table->dateTime('cancelled_at')->nullable();
            $table->string('confirm_token', 64)->unique();
            $table->string('manage_token', 64)->unique();
            $table->timestamps();

            $table->index(['tenant_id', 'unit_id', 'start_at', 'end_at'], 'reservations_tenant_unit_window_idx');
            $table->index(['tenant_id', 'guest_email'], 'reservations_tenant_guest_email_idx');
            $table->index(['expires_at'], 'reservations_expires_at_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reservations');

        Schema::table('units', function (Blueprint $table) {
            $table->dropColumn('allow_reservations');
        });

        Schema::table('categories', function (Blueprint $table) {
            $table->dropColumn('is_reservable');
        });
    }
};
