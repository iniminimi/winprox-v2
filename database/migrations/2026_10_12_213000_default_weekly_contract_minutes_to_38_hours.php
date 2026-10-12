<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('workers')->whereNull('weekly_contract_minutes')->update([
            'weekly_contract_minutes' => 2280,
        ]);

        Schema::table('workers', function (Blueprint $table) {
            $table->unsignedInteger('weekly_contract_minutes')->nullable()->default(2280)->change();
        });
    }

    public function down(): void
    {
        Schema::table('workers', function (Blueprint $table) {
            $table->unsignedInteger('weekly_contract_minutes')->nullable()->default(null)->change();
        });
    }
};
