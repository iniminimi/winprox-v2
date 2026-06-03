<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('documents', function (Blueprint $table) {
            $table->foreignId('category_id')
                ->nullable()
                ->after('unit_id')
                ->constrained('categories')
                ->nullOnDelete();
        });

        Schema::table('announcements', function (Blueprint $table) {
            $table->foreignId('category_id')
                ->nullable()
                ->after('unit_id')
                ->constrained('categories')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('documents', function (Blueprint $table) {
            $table->dropConstrainedForeignId('category_id');
        });

        Schema::table('announcements', function (Blueprint $table) {
            $table->dropConstrainedForeignId('category_id');
        });
    }
};
