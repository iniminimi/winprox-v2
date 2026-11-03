<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('worker_leave_entitlements', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('worker_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('year');
            $table->decimal('days', 5, 1);
            $table->timestamps();

            $table->unique(['worker_id', 'year']);
        });

        Schema::table('absence_requests', function (Blueprint $table): void {
            $table->decimal('day_portion', 2, 1)->default(1)->after('date_to');
        });

        Schema::table('planned_shifts', function (Blueprint $table): void {
            $table->decimal('day_portion', 2, 1)->nullable()->after('end_time');
        });
    }

    public function down(): void
    {
        Schema::table('planned_shifts', function (Blueprint $table): void {
            $table->dropColumn('day_portion');
        });

        Schema::table('absence_requests', function (Blueprint $table): void {
            $table->dropColumn('day_portion');
        });

        Schema::dropIfExists('worker_leave_entitlements');
    }
};
