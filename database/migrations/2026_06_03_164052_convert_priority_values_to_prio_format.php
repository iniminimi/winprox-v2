<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        DB::statement("UPDATE tasks SET priority = 'prio_1' WHERE priority = 'critical'");
        DB::statement("UPDATE tasks SET priority = 'prio_2' WHERE priority = 'high'");
        DB::statement("UPDATE tasks SET priority = 'prio_3' WHERE priority = 'medium'");
        DB::statement("UPDATE tasks SET priority = 'prio_4' WHERE priority = 'low'");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::statement("UPDATE tasks SET priority = 'critical' WHERE priority = 'prio_1'");
        DB::statement("UPDATE tasks SET priority = 'high' WHERE priority = 'prio_2'");
        DB::statement("UPDATE tasks SET priority = 'medium' WHERE priority = 'prio_3'");
        DB::statement("UPDATE tasks SET priority = 'low' WHERE priority = 'prio_4'");
    }
};
