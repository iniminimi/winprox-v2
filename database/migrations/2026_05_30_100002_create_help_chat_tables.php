<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('help_chat_unanswered_questions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('locale', 8);
            $table->text('question');
            $table->timestamps();
        });

        Schema::create('help_chat_knowledge_base_entries', function (Blueprint $table) {
            $table->id();
            $table->string('locale', 8)->index();
            $table->string('match_key')->index();
            $table->json('patterns');
            $table->text('answer');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('help_chat_knowledge_base_entries');
        Schema::dropIfExists('help_chat_unanswered_questions');
    }
};
