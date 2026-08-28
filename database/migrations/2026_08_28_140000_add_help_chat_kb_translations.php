<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('help_chat_knowledge_base_entries', 'original_language')) {
            Schema::table('help_chat_knowledge_base_entries', function (Blueprint $table) {
                $table->string('original_language', 5)->nullable()->after('id');
            });

            if (Schema::hasColumn('help_chat_knowledge_base_entries', 'locale')) {
                DB::table('help_chat_knowledge_base_entries')->update([
                    'original_language' => DB::raw("CASE WHEN locale = '*' THEN 'nl' ELSE locale END"),
                ]);

                Schema::table('help_chat_knowledge_base_entries', function (Blueprint $table) {
                    $table->dropIndex(['locale']);
                    $table->dropColumn('locale');
                });
            } else {
                DB::table('help_chat_knowledge_base_entries')->whereNull('original_language')->update([
                    'original_language' => 'nl',
                ]);
            }
        }

        if (Schema::hasTable('help_chat_knowledge_base_entry_translations')) {
            return;
        }

        Schema::create('help_chat_knowledge_base_entry_translations', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('help_chat_knowledge_base_entry_id');
            $table->string('locale', 5);
            $table->json('patterns')->nullable();
            $table->text('answer')->nullable();
            $table->string('status', 20)->default('pending');
            $table->timestamps();

            $table->unique(['help_chat_knowledge_base_entry_id', 'locale'], 'hc_kb_entry_locale_uq');
            $table->index(['status', 'locale'], 'hc_kb_entry_trans_status_idx');
        });

        Schema::table('help_chat_knowledge_base_entry_translations', function (Blueprint $table) {
            $table->foreign('help_chat_knowledge_base_entry_id', 'hc_kb_entry_trans_entry_fk')
                ->references('id')
                ->on('help_chat_knowledge_base_entries')
                ->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('help_chat_knowledge_base_entry_translations');

        Schema::table('help_chat_knowledge_base_entries', function (Blueprint $table) {
            $table->string('locale', 8)->nullable()->after('id');
        });

        DB::table('help_chat_knowledge_base_entries')->update([
            'locale' => DB::raw('original_language'),
        ]);

        Schema::table('help_chat_knowledge_base_entries', function (Blueprint $table) {
            $table->dropColumn('original_language');
            $table->index('locale');
        });
    }
};
