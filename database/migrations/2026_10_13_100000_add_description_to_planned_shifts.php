<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('planned_shifts', function (Blueprint $table) {
            $table->string('description', 500)->nullable()->after('break_minutes');
        });
    }

    public function down(): void
    {
        Schema::table('planned_shifts', function (Blueprint $table) {
            $table->dropColumn('description');
        });
    }
};
