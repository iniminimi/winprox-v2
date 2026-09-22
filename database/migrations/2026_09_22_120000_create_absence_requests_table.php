<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('absence_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('worker_id')->constrained()->cascadeOnDelete();
            $table->string('kind', 16);
            $table->foreignId('shift_type_id')->nullable()->constrained('shift_types')->nullOnDelete();
            $table->date('date_from');
            $table->date('date_to');
            $table->string('description', 500)->nullable();
            $table->string('status', 16);
            $table->string('decision_description', 500)->nullable();
            $table->foreignId('decided_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('decided_at')->nullable();
            $table->json('replaced_shifts')->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'status']);
            $table->index(['worker_id', 'status']);
            $table->index(['tenant_id', 'date_from', 'date_to']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('absence_requests');
    }
};
