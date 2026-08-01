<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('unit_check_lists', function (Blueprint $table) {
            $table->string('original_language', 5)->nullable()->after('name');
        });

        DB::table('unit_check_lists')->whereNull('original_language')->update(['original_language' => 'nl']);

        Schema::create('unit_check_list_translations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('unit_check_list_id')->constrained()->cascadeOnDelete();
            $table->string('locale', 5);
            $table->string('name')->nullable();
            $table->json('items')->nullable();
            $table->string('status', 20)->default('pending');
            $table->timestamps();

            $table->unique(['unit_check_list_id', 'locale']);
            $table->index(['status', 'locale']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('unit_check_list_translations');

        Schema::table('unit_check_lists', function (Blueprint $table) {
            $table->dropColumn('original_language');
        });
    }
};
