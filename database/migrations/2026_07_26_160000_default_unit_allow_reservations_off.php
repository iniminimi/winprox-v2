<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('units', function (Blueprint $table) {
            $table->boolean('allow_reservations')->default(false)->change();
        });

        // Opt-in per unit: bestaande rijen terugzetten naar uit (categorie blijft de hoofdschakelaar).
        DB::table('units')->update(['allow_reservations' => false]);
    }

    public function down(): void
    {
        Schema::table('units', function (Blueprint $table) {
            $table->boolean('allow_reservations')->default(true)->change();
        });
    }
};
