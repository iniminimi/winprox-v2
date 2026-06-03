<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tasks', function (Blueprint $table): void {
            $table->foreignId('carryover_from_task_id')
                ->nullable()
                ->after('cycle_number')
                ->constrained('tasks')
                ->nullOnDelete();
            $table->timestamp('not_executed_at')->nullable()->after('carryover_from_task_id');
            $table->unsignedSmallInteger('late_by_days')->nullable()->after('not_executed_at');
            $table->timestamp('hold_started_at')->nullable()->after('late_by_days');
            $table->unsignedInteger('hold_total_minutes')->default(0)->after('hold_started_at');
            $table->string('status_reason', 255)->nullable()->after('hold_total_minutes');
        });
    }

    public function down(): void
    {
        Schema::table('tasks', function (Blueprint $table): void {
            $table->dropForeign(['carryover_from_task_id']);
            $table->dropColumn([
                'carryover_from_task_id',
                'not_executed_at',
                'late_by_days',
                'hold_started_at',
                'hold_total_minutes',
                'status_reason',
            ]);
        });
    }
};
