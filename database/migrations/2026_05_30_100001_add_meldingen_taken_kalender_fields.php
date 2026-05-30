<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('issues', function (Blueprint $table) {
            $table->string('source', 20)->default('manager')->after('description');
            $table->boolean('is_recurring')->default(false)->after('source');
            $table->unsignedSmallInteger('recurrence_interval_value')->nullable()->after('is_recurring');
            $table->string('recurrence_interval_unit', 20)->nullable()->after('recurrence_interval_value');
            $table->unsignedSmallInteger('recurrence_lead_days')->default(30)->after('recurrence_interval_unit');
            $table->boolean('recurrence_active')->default(true)->after('recurrence_lead_days');
            $table->timestamp('recurrence_paused_at')->nullable()->after('recurrence_active');
            $table->timestamp('recurrence_next_due_at')->nullable()->after('recurrence_paused_at');
            $table->timestamp('recurrence_last_task_created_at')->nullable()->after('recurrence_next_due_at');

            $table->index(['tenant_id', 'is_recurring']);
            $table->index(['tenant_id', 'recurrence_next_due_at']);
        });

        Schema::table('tasks', function (Blueprint $table) {
            $table->date('scheduled_for')->nullable()->after('note');
            $table->timestamp('due_at')->nullable()->after('scheduled_for');
            $table->boolean('is_recurring_cycle')->default(false)->after('due_at');
            $table->foreignId('recurrence_issue_id')->nullable()->after('is_recurring_cycle')
                ->constrained('issues')->nullOnDelete();
            $table->unsignedInteger('cycle_number')->nullable()->after('recurrence_issue_id');

            $table->index(['tenant_id', 'scheduled_for']);
            $table->index(['tenant_id', 'due_at']);
            $table->index(['tenant_id', 'is_recurring_cycle']);
        });
    }

    public function down(): void
    {
        Schema::table('tasks', function (Blueprint $table) {
            $table->dropForeign(['recurrence_issue_id']);
            $table->dropColumn([
                'scheduled_for',
                'due_at',
                'is_recurring_cycle',
                'recurrence_issue_id',
                'cycle_number',
            ]);
        });

        Schema::table('issues', function (Blueprint $table) {
            $table->dropColumn([
                'source',
                'is_recurring',
                'recurrence_interval_value',
                'recurrence_interval_unit',
                'recurrence_lead_days',
                'recurrence_active',
                'recurrence_paused_at',
                'recurrence_next_due_at',
                'recurrence_last_task_created_at',
            ]);
        });
    }
};
