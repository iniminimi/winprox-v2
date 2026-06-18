<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::getConnection()->getDriverName() === 'sqlite') {
            DB::statement('ALTER TABLE tasks RENAME COLUMN note TO description');
            DB::statement('ALTER TABLE issue_updates RENAME COLUMN body TO description');
            Schema::table('announcements', function (Blueprint $table) {
                $table->dropColumn('title');
            });
            DB::statement('ALTER TABLE announcements RENAME COLUMN body TO description');
            DB::statement('ALTER TABLE issue_translations RENAME COLUMN text TO description');

            return;
        }

        DB::statement('ALTER TABLE tasks CHANGE note description TEXT NULL');
        DB::statement('ALTER TABLE issue_updates CHANGE body description TEXT NULL');
        DB::statement('ALTER TABLE announcements DROP COLUMN title');
        DB::statement('ALTER TABLE announcements CHANGE body description TEXT NOT NULL');
        DB::statement('ALTER TABLE issue_translations CHANGE text description TEXT NULL');
    }

    public function down(): void
    {
        if (Schema::getConnection()->getDriverName() === 'sqlite') {
            DB::statement('ALTER TABLE issue_translations RENAME COLUMN description TO text');
            DB::statement('ALTER TABLE announcements RENAME COLUMN description TO body');
            Schema::table('announcements', function (Blueprint $table) {
                $table->string('title')->default('');
            });
            DB::statement('ALTER TABLE issue_updates RENAME COLUMN description TO body');
            DB::statement('ALTER TABLE tasks RENAME COLUMN description TO note');

            return;
        }

        DB::statement('ALTER TABLE issue_translations CHANGE description text TEXT NULL');
        DB::statement('ALTER TABLE announcements CHANGE description body TEXT NOT NULL');
        DB::statement("ALTER TABLE announcements ADD title VARCHAR(255) NOT NULL DEFAULT '' AFTER unit_id");
        DB::statement('ALTER TABLE issue_updates CHANGE description body TEXT NULL');
        DB::statement('ALTER TABLE tasks CHANGE description note TEXT NULL');
    }
};
