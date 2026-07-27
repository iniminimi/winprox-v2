<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('categories', function (Blueprint $table) {
            $table->string('original_language', 5)->nullable()->after('name');
        });

        DB::table('categories')->whereNull('original_language')->update(['original_language' => 'nl']);

        Schema::create('category_translations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('category_id')->constrained()->cascadeOnDelete();
            $table->string('locale', 5);
            $table->string('name')->nullable();
            $table->string('status', 20)->default('pending');
            $table->timestamps();

            $table->unique(['category_id', 'locale']);
            $table->index(['status', 'locale']);
        });

        Schema::table('internal_teams', function (Blueprint $table) {
            $table->string('original_language', 5)->nullable()->after('name');
        });

        DB::table('internal_teams')->whereNull('original_language')->update(['original_language' => 'nl']);

        Schema::create('internal_team_translations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('internal_team_id')->constrained()->cascadeOnDelete();
            $table->string('locale', 5);
            $table->string('name')->nullable();
            $table->string('status', 20)->default('pending');
            $table->timestamps();

            $table->unique(['internal_team_id', 'locale']);
            $table->index(['status', 'locale']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('internal_team_translations');
        Schema::dropIfExists('category_translations');

        Schema::table('internal_teams', function (Blueprint $table) {
            $table->dropColumn('original_language');
        });

        Schema::table('categories', function (Blueprint $table) {
            $table->dropColumn('original_language');
        });
    }
};
