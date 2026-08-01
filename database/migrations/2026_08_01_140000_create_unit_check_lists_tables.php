<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('unit_check_lists', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['tenant_id', 'is_active']);
        });

        Schema::create('unit_check_list_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('unit_check_list_id')->constrained('unit_check_lists')->cascadeOnDelete();
            $table->string('label', 200);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index(['unit_check_list_id', 'sort_order']);
        });

        Schema::table('units', function (Blueprint $table) {
            $table->foreignId('unit_check_list_id')
                ->nullable()
                ->after('category_id')
                ->constrained('unit_check_lists')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('units', function (Blueprint $table) {
            $table->dropConstrainedForeignId('unit_check_list_id');
        });

        Schema::dropIfExists('unit_check_list_items');
        Schema::dropIfExists('unit_check_lists');
    }
};
