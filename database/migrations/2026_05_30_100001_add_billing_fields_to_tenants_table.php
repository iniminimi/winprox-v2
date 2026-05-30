<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            $table->timestamp('trial_ends_at')->nullable()->after('name');
            $table->string('billing_plan')->nullable()->after('trial_ends_at');
            $table->timestamp('billing_active_until')->nullable()->after('billing_plan');
            $table->boolean('is_active')->default(true)->after('billing_active_until');
            $table->string('stripe_customer_id')->nullable()->after('is_active');
        });
    }

    public function down(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            $table->dropColumn([
                'trial_ends_at',
                'billing_plan',
                'billing_active_until',
                'is_active',
                'stripe_customer_id',
            ]);
        });
    }
};
