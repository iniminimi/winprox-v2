<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('workers', function (Blueprint $table) {
            // Teamleader mag (in het veld-portaal) iconen van collega-workers vrijgeven.
            $table->boolean('is_teamleader')->default(false)->after('is_active');
        });
    }

    public function down(): void
    {
        Schema::table('workers', function (Blueprint $table) {
            $table->dropColumn('is_teamleader');
        });
    }
};
