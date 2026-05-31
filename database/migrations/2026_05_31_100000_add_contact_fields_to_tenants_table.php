<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            $table->string('email')->nullable()->after('name');
            $table->string('phone', 50)->nullable()->after('email');
            $table->string('street')->nullable()->after('phone');
            $table->string('house_number', 32)->nullable()->after('street');
            $table->string('postal_code', 32)->nullable()->after('house_number');
            $table->string('city', 128)->nullable()->after('postal_code');
            $table->string('country_code', 2)->nullable()->after('city');
        });
    }

    public function down(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            $table->dropColumn([
                'email',
                'phone',
                'street',
                'house_number',
                'postal_code',
                'city',
                'country_code',
            ]);
        });
    }
};
