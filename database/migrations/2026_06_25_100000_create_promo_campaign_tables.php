<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('promo_campaigns', function (Blueprint $table) {
            $table->id();
            $table->string('slug', 64)->unique();
            $table->string('name', 255);
            $table->string('locale', 5)->default('nl');
            $table->longText('letter_body_html')->nullable();
            $table->string('email_subject', 255)->nullable();
            $table->longText('email_body_html')->nullable();
            $table->string('flow_image_path', 500)->nullable();
            $table->json('column_mapping')->nullable();
            $table->foreignId('created_by')->constrained('users')->cascadeOnDelete();
            $table->timestamps();
        });

        Schema::create('promo_campaign_imports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('promo_campaign_id')->constrained('promo_campaigns')->cascadeOnDelete();
            $table->string('original_filename', 255);
            $table->unsignedInteger('row_count')->default(0);
            $table->foreignId('imported_by')->constrained('users')->cascadeOnDelete();
            $table->timestamp('imported_at');
            $table->timestamps();
        });

        Schema::create('promo_campaign_targets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('promo_campaign_id')->constrained('promo_campaigns')->cascadeOnDelete();
            $table->foreignId('promo_campaign_import_id')->constrained('promo_campaign_imports')->cascadeOnDelete();
            $table->foreignId('promo_recipient_id')->nullable()->constrained('promo_recipients')->nullOnDelete();
            $table->json('raw_row');
            $table->string('name', 255);
            $table->string('email', 255)->nullable();
            $table->string('street_address', 500)->nullable();
            $table->string('postal_code', 32)->nullable();
            $table->string('city', 255)->nullable();
            $table->string('docx_filename', 255)->nullable();
            $table->timestamp('generated_at')->nullable();
            $table->timestamps();

            $table->index(['promo_campaign_id', 'name']);
        });

        Schema::create('promo_campaign_email_sends', function (Blueprint $table) {
            $table->id();
            $table->foreignId('promo_campaign_id')->constrained('promo_campaigns')->cascadeOnDelete();
            $table->foreignId('promo_campaign_target_id')->constrained('promo_campaign_targets')->cascadeOnDelete();
            $table->string('recipient_email', 255);
            $table->string('status', 32);
            $table->text('error_message')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->foreignId('created_by')->constrained('users')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['promo_campaign_id', 'promo_campaign_target_id'], 'promo_campaign_send_unique');
            $table->index(['promo_campaign_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('promo_campaign_email_sends');
        Schema::dropIfExists('promo_campaign_targets');
        Schema::dropIfExists('promo_campaign_imports');
        Schema::dropIfExists('promo_campaigns');
    }
};
