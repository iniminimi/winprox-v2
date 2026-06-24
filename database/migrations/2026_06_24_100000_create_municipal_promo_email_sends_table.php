<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('municipal_promo_email_sends', function (Blueprint $table) {
            $table->id();
            $table->string('campaign', 64);
            $table->foreignId('promo_recipient_id')->nullable()->constrained('promo_recipients')->nullOnDelete();
            $table->string('municipality_name', 255);
            $table->string('recipient_email', 255);
            $table->string('docx_filename', 255);
            $table->string('status', 32);
            $table->text('error_message')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->foreignId('created_by')->constrained('users')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['campaign', 'municipality_name']);
            $table->index(['campaign', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('municipal_promo_email_sends');
    }
};
