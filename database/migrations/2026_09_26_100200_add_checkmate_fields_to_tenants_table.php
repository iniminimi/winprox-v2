<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            $table->boolean('checkmate_mode')->default(false)->after('has_iot_module');
            $table->unsignedSmallInteger('billing_seats_qty')->nullable()->after('billing_units_cap');
        });
    }

    public function down(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            $table->dropColumn(['checkmate_mode', 'billing_seats_qty']);
        });
    }
};
