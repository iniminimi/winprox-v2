<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('issues', function (Blueprint $table) {
            $table->string('original_language', 5)->nullable()->after('description');
        });

        Schema::create('issue_translations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('issue_id')->constrained()->cascadeOnDelete();
            $table->string('locale', 5);
            $table->text('text')->nullable();
            $table->string('status', 20)->default('pending');
            $table->timestamps();

            $table->unique(['issue_id', 'locale']);
            $table->index(['status', 'locale']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('issue_translations');

        Schema::table('issues', function (Blueprint $table) {
            $table->dropColumn('original_language');
        });
    }
};
