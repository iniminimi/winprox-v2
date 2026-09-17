<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('email_action_tokens', function (Blueprint $table) {
            $table->id();
            $table->string('email');
            $table->string('token', 8)->unique();
            $table->string('purpose', 16);
            $table->timestamps();

            $table->unique(['email', 'purpose']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('email_action_tokens');
    }
};
