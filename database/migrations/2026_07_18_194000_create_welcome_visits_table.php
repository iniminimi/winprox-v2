<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('welcome_visits', function (Blueprint $table) {
            $table->id();
            $table->timestamp('visited_at');
            $table->string('locale', 5);
            $table->string('visitor_hash', 64);
            $table->string('utm_source', 64)->nullable();
            $table->string('utm_medium', 64)->nullable();
            $table->string('utm_campaign', 128)->nullable();

            $table->index(['visited_at']);
            $table->index(['visitor_hash', 'visited_at']);
            $table->index(['locale', 'visited_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('welcome_visits');
    }
};
