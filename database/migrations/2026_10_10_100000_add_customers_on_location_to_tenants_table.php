<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            $table->boolean('customers_on_location')->default(false)->after('checkmate_mode');
        });

        // Bestaande Checkmate-tenants: zelfde mental model als customers_on_location.
        DB::table('tenants')
            ->where('checkmate_mode', true)
            ->update(['customers_on_location' => true]);
    }

    public function down(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            $table->dropColumn('customers_on_location');
        });
    }
};
