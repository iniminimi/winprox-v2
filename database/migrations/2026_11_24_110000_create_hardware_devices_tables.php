<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ops_portals', function (Blueprint $table) {
            $table->id();
            $table->string('token', 40)->unique();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('hardware_devices', function (Blueprint $table) {
            $table->id();
            $table->string('brand', 64);
            $table->string('type', 32);
            $table->string('model', 64)->nullable();
            $table->string('sku', 64)->nullable();
            $table->string('serial_number', 64)->unique();
            $table->string('mac', 32)->nullable()->unique();
            $table->foreignId('tenant_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('clock_point_id')->nullable()->constrained()->nullOnDelete();
            $table->string('status', 16);
            $table->string('condition', 16)->default('new');
            $table->timestamp('sn_scanned_at')->nullable();
            $table->timestamp('mac_set_at')->nullable();
            $table->timestamp('assigned_to_tenant_at')->nullable();
            $table->timestamp('installed_at')->nullable();
            $table->timestamp('last_seen_at')->nullable();
            $table->timestamp('returned_at')->nullable();
            $table->timestamp('retired_at')->nullable();
            $table->string('purchase_ref', 64)->nullable();
            $table->timestamp('purchased_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['brand', 'type']);
            $table->index('status');
            $table->index('tenant_id');
        });

        Schema::create('hardware_device_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('hardware_device_id')->constrained()->cascadeOnDelete();
            $table->string('event_type', 32);
            $table->foreignId('tenant_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('clock_point_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('actor_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->json('payload')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['hardware_device_id', 'created_at']);
            $table->index('event_type');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('hardware_device_events');
        Schema::dropIfExists('hardware_devices');
        Schema::dropIfExists('ops_portals');
    }
};
