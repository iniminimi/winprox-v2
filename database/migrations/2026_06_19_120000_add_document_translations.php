<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('documents', function (Blueprint $table) {
            $table->string('original_language', 5)->nullable()->after('description');
        });

        DB::table('documents')->whereNull('original_language')->update(['original_language' => 'nl']);

        Schema::create('document_translations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('document_id')->constrained()->cascadeOnDelete();
            $table->string('locale', 5);
            $table->text('description')->nullable();
            $table->string('status', 20)->default('pending');
            $table->timestamps();

            $table->unique(['document_id', 'locale']);
            $table->index(['status', 'locale']);
        });

        if (Schema::getConnection()->getDriverName() !== 'sqlite') {
            DB::statement('ALTER TABLE document_translations MODIFY description VARCHAR(1500) NULL');
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('document_translations');

        Schema::table('documents', function (Blueprint $table) {
            $table->dropColumn('original_language');
        });
    }
};
