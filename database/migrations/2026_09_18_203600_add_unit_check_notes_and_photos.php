<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('unit_checks', function (Blueprint $table) {
            $table->string('description', 500)->nullable()->after('checklist_items');
        });

        Schema::create('unit_check_photos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('unit_check_id')->constrained()->cascadeOnDelete();
            $table->string('path');
            $table->timestamp('created_at')->useCurrent();

            $table->index(['tenant_id', 'unit_check_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('unit_check_photos');

        Schema::table('unit_checks', function (Blueprint $table) {
            $table->dropColumn('description');
        });
    }
};
