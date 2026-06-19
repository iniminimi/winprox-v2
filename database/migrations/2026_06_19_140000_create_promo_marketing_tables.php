<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('promo_recipients', function (Blueprint $table) {
            $table->id();
            $table->string('token', 32)->unique();
            $table->string('label', 255);
            $table->string('note', 1000)->nullable();
            $table->foreignId('created_by')->constrained('users')->cascadeOnDelete();
            $table->timestamps();
        });

        Schema::create('promo_visits', function (Blueprint $table) {
            $table->id();
            $table->foreignId('promo_recipient_id')->nullable()->constrained('promo_recipients')->nullOnDelete();
            $table->string('locale', 5);
            $table->timestamp('visited_at');
            $table->index(['promo_recipient_id', 'visited_at']);
            $table->index(['visited_at']);
        });

        Schema::create('promo_video_plays', function (Blueprint $table) {
            $table->id();
            $table->foreignId('promo_recipient_id')->constrained('promo_recipients')->cascadeOnDelete();
            $table->string('video_key', 64);
            $table->string('locale', 5);
            $table->timestamp('played_at');
            $table->unique(['promo_recipient_id', 'video_key']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('promo_video_plays');
        Schema::dropIfExists('promo_visits');
        Schema::dropIfExists('promo_recipients');
    }
};
