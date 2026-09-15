<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('shift_types', function (Blueprint $table) {
            $table->string('kind', 16)->default('work')->after('label');
            $table->char('start_time', 5)->nullable()->change();
            $table->char('end_time', 5)->nullable()->change();
        });

        Schema::table('planned_shifts', function (Blueprint $table) {
            $table->string('kind', 16)->default('work')->after('shift_type_id');
            $table->char('start_time', 5)->nullable()->change();
            $table->char('end_time', 5)->nullable()->change();
        });

        Schema::create('worker_notifications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('worker_id')->constrained()->cascadeOnDelete();
            $table->string('type', 32);
            $table->string('reference_id', 32);
            $table->timestamp('read_at')->nullable();
            $table->timestamps();

            $table->unique(['worker_id', 'type', 'reference_id'], 'worker_notifications_worker_type_ref_unique');
            $table->index(['tenant_id', 'worker_id', 'read_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('worker_notifications');

        Schema::table('planned_shifts', function (Blueprint $table) {
            $table->dropColumn('kind');
            $table->char('start_time', 5)->nullable(false)->change();
            $table->char('end_time', 5)->nullable(false)->change();
        });

        Schema::table('shift_types', function (Blueprint $table) {
            $table->dropColumn('kind');
            $table->char('start_time', 5)->nullable(false)->change();
            $table->char('end_time', 5)->nullable(false)->change();
        });
    }
};
