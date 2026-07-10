<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('esg_indicators', function (Blueprint $table) {
            $table->string('original_language', 5)->nullable()->after('name');
        });

        DB::table('esg_indicators')->whereNull('original_language')->update(['original_language' => 'nl']);

        Schema::create('esg_indicator_translations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('esg_indicator_id')->constrained()->cascadeOnDelete();
            $table->string('locale', 5);
            $table->string('name')->nullable();
            $table->json('options')->nullable();
            $table->string('status', 20)->default('pending');
            $table->timestamps();

            $table->unique(['esg_indicator_id', 'locale']);
            $table->index(['status', 'locale']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('esg_indicator_translations');

        Schema::table('esg_indicators', function (Blueprint $table) {
            $table->dropColumn('original_language');
        });
    }
};
