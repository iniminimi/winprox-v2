<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('issue_round_stops', function (Blueprint $table) {
            $table->id();
            $table->foreignId('issue_id')->constrained()->cascadeOnDelete();
            $table->foreignId('unit_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();

            $table->unique(['issue_id', 'unit_id']);
            $table->index(['issue_id', 'sort_order']);
        });

        Schema::create('task_round_stop_skips', function (Blueprint $table) {
            $table->id();
            $table->foreignId('task_id')->constrained()->cascadeOnDelete();
            $table->foreignId('unit_id')->constrained()->cascadeOnDelete();
            $table->foreignId('worker_id')->nullable()->constrained()->nullOnDelete();
            $table->string('reason', 500);
            $table->timestamps();

            $table->unique(['task_id', 'unit_id']);
            $table->index(['task_id', 'unit_id']);
        });

        Schema::table('unit_checks', function (Blueprint $table) {
            $table->index(['task_id', 'unit_id'], 'unit_checks_task_id_unit_id_index');
        });
    }

    public function down(): void
    {
        Schema::table('unit_checks', function (Blueprint $table) {
            $table->dropIndex('unit_checks_task_id_unit_id_index');
        });

        Schema::dropIfExists('task_round_stop_skips');
        Schema::dropIfExists('issue_round_stops');
    }
};
