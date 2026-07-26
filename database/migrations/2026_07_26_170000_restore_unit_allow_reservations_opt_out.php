<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Categorie = hoofdschakelaar; unit-vinkje is opt-out (standaard aan).
        Schema::table('units', function (Blueprint $table) {
            $table->boolean('allow_reservations')->default(true)->change();
        });

        DB::table('units')->update(['allow_reservations' => true]);
    }

    public function down(): void
    {
        Schema::table('units', function (Blueprint $table) {
            $table->boolean('allow_reservations')->default(false)->change();
        });
    }
};
