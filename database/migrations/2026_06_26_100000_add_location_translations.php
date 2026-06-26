<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('locations', function (Blueprint $table) {
            $table->string('original_language', 5)->nullable()->after('name');
        });

        DB::table('locations')->whereNull('original_language')->update(['original_language' => 'nl']);

        Schema::create('location_translations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('location_id')->constrained()->cascadeOnDelete();
            $table->string('locale', 5);
            $table->string('name')->nullable();
            $table->string('status', 20)->default('pending');
            $table->timestamps();

            $table->unique(['location_id', 'locale']);
            $table->index(['status', 'locale']);
        });

        if (Schema::getConnection()->getDriverName() !== 'sqlite') {
            DB::statement('ALTER TABLE location_translations MODIFY name VARCHAR(255) NULL');
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('location_translations');

        Schema::table('locations', function (Blueprint $table) {
            $table->dropColumn('original_language');
        });
    }
};
