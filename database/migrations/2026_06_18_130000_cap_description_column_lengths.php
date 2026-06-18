<?php

use App\Support\Validation\TextDescriptionLimits;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $source = TextDescriptionLimits::MAX;
        $translation = TextDescriptionLimits::TRANSLATION_MAX;

        $this->truncateColumn('issues', 'description', $source);
        $this->truncateColumn('tasks', 'description', $source);
        $this->truncateColumn('issue_updates', 'description', $source);
        $this->truncateColumn('announcements', 'description', $source);
        $this->truncateColumn('documents', 'description', $source);
        $this->truncateColumn('units', 'description', $source);
        $this->truncateColumn('issue_translations', 'description', $translation);

        if (Schema::getConnection()->getDriverName() === 'sqlite') {
            return;
        }

        DB::statement('ALTER TABLE issues MODIFY description VARCHAR('.$source.') NOT NULL');
        DB::statement('ALTER TABLE tasks MODIFY description VARCHAR('.$source.') NULL');
        DB::statement('ALTER TABLE issue_updates MODIFY description VARCHAR('.$source.') NULL');
        DB::statement('ALTER TABLE announcements MODIFY description VARCHAR('.$source.') NOT NULL');
        DB::statement('ALTER TABLE documents MODIFY description VARCHAR('.$source.') NULL');
        DB::statement('ALTER TABLE units MODIFY description VARCHAR('.$source.') NULL');
        DB::statement('ALTER TABLE issue_translations MODIFY description VARCHAR('.$translation.') NULL');
    }

    public function down(): void
    {
        if (Schema::getConnection()->getDriverName() === 'sqlite') {
            return;
        }

        DB::statement('ALTER TABLE issues MODIFY description TEXT NOT NULL');
        DB::statement('ALTER TABLE tasks MODIFY description TEXT NULL');
        DB::statement('ALTER TABLE issue_updates MODIFY description TEXT NULL');
        DB::statement('ALTER TABLE announcements MODIFY description TEXT NOT NULL');
        DB::statement('ALTER TABLE documents MODIFY description TEXT NULL');
        DB::statement('ALTER TABLE units MODIFY description TEXT NULL');
        DB::statement('ALTER TABLE issue_translations MODIFY description TEXT NULL');
    }

    private function truncateColumn(string $table, string $column, int $maxLength): void
    {
        if (Schema::getConnection()->getDriverName() === 'sqlite') {
            DB::statement(
                'UPDATE '.$table.' SET '.$column.' = SUBSTR('.$column.', 1, ?) WHERE LENGTH('.$column.') > ?',
                [$maxLength, $maxLength],
            );

            return;
        }

        DB::statement(
            'UPDATE '.$table.' SET '.$column.' = LEFT('.$column.', ?) WHERE CHAR_LENGTH('.$column.') > ?',
            [$maxLength, $maxLength],
        );
    }
};
