<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('announcements', function (Blueprint $table) {
            $table->string('original_language', 5)->nullable()->after('description');
        });

        DB::table('announcements')->whereNull('original_language')->update(['original_language' => 'nl']);

        Schema::create('announcement_translations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('announcement_id')->constrained()->cascadeOnDelete();
            $table->string('locale', 5);
            $table->text('description')->nullable();
            $table->string('status', 20)->default('pending');
            $table->timestamps();

            $table->unique(['announcement_id', 'locale']);
            $table->index(['status', 'locale']);
        });

        if (Schema::getConnection()->getDriverName() !== 'sqlite') {
            DB::statement('ALTER TABLE announcement_translations MODIFY description VARCHAR(1500) NULL');
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('announcement_translations');

        Schema::table('announcements', function (Blueprint $table) {
            $table->dropColumn('original_language');
        });
    }
};
