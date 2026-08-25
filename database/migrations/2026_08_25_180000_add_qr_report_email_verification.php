<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('categories', function (Blueprint $table) {
            $table->boolean('require_reporter_email_verification')->default(false)->after('require_reporter_contact');
        });

        Schema::table('units', function (Blueprint $table) {
            $table->boolean('require_reporter_email_verification')->default(false)->after('require_reporter_contact');
        });

        Schema::create('qr_report_email_holds', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('unit_id')->constrained()->cascadeOnDelete();
            $table->text('description');
            $table->string('reporter_name')->nullable();
            $table->string('reporter_contact');
            $table->string('original_language', 16)->nullable();
            $table->json('photo_paths')->nullable();
            $table->string('token', 64)->unique();
            $table->timestamp('expires_at');
            $table->timestamp('confirmed_at')->nullable();
            $table->foreignId('issue_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamps();
            $table->index(['expires_at', 'confirmed_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('qr_report_email_holds');

        Schema::table('units', function (Blueprint $table) {
            $table->dropColumn('require_reporter_email_verification');
        });

        Schema::table('categories', function (Blueprint $table) {
            $table->dropColumn('require_reporter_email_verification');
        });
    }
};
