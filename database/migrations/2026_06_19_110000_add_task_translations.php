<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tasks', function (Blueprint $table) {
            $table->string('original_language', 5)->nullable()->after('description');
        });

        if (Schema::hasTable('issues')) {
            $driver = Schema::getConnection()->getDriverName();

            if ($driver === 'sqlite') {
                DB::table('tasks')->whereNull('original_language')->update(['original_language' => 'nl']);
            } else {
                DB::statement(
                    'UPDATE tasks t INNER JOIN issues i ON t.issue_id = i.id '
                    ."SET t.original_language = COALESCE(i.original_language, 'nl') "
                    .'WHERE t.original_language IS NULL',
                );
            }
        }

        DB::table('tasks')->whereNull('original_language')->update(['original_language' => 'nl']);

        Schema::create('task_translations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('task_id')->constrained()->cascadeOnDelete();
            $table->string('locale', 5);
            $table->text('description')->nullable();
            $table->string('status', 20)->default('pending');
            $table->timestamps();

            $table->unique(['task_id', 'locale']);
            $table->index(['status', 'locale']);
        });

        if (Schema::getConnection()->getDriverName() !== 'sqlite') {
            DB::statement('ALTER TABLE task_translations MODIFY description VARCHAR(1500) NULL');
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('task_translations');

        Schema::table('tasks', function (Blueprint $table) {
            $table->dropColumn('original_language');
        });
    }
};
