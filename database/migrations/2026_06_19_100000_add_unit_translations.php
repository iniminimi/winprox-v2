<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('units', function (Blueprint $table) {
            $table->string('original_language', 5)->nullable()->after('description');
        });

        DB::table('units')->whereNull('original_language')->update(['original_language' => 'nl']);

        Schema::create('unit_translations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('unit_id')->constrained()->cascadeOnDelete();
            $table->string('locale', 5);
            $table->string('name')->nullable();
            $table->text('description')->nullable();
            $table->string('status', 20)->default('pending');
            $table->timestamps();

            $table->unique(['unit_id', 'locale']);
            $table->index(['status', 'locale']);
        });

        if (Schema::getConnection()->getDriverName() !== 'sqlite') {
            DB::statement('ALTER TABLE unit_translations MODIFY description VARCHAR(1500) NULL');
            DB::statement('ALTER TABLE unit_translations MODIFY name VARCHAR(255) NULL');
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('unit_translations');

        Schema::table('units', function (Blueprint $table) {
            $table->dropColumn('original_language');
        });
    }
};
