<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('unit_checks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('unit_id')->constrained()->cascadeOnDelete();
            $table->foreignId('location_id')->constrained()->cascadeOnDelete();
            $table->foreignId('worker_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('internal_team_id')->nullable()->constrained()->nullOnDelete();
            $table->string('result', 16);
            $table->string('source', 16)->default('portal');
            $table->dateTime('checked_at');
            $table->decimal('latitude', 10, 8)->nullable();
            $table->decimal('longitude', 11, 8)->nullable();
            $table->foreignId('task_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('issue_id')->nullable()->constrained()->nullOnDelete();
            $table->json('checklist_items')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['tenant_id', 'checked_at']);
            $table->index(['unit_id', 'checked_at']);
            $table->index(['location_id', 'checked_at']);
            $table->index(['result', 'checked_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('unit_checks');
    }
};
