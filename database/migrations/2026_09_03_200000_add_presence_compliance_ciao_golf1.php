<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            $table->string('enterprise_number', 10)->nullable()->after('country_code');
            $table->string('foreign_vat_number', 255)->nullable()->after('enterprise_number');
            $table->boolean('presence_compliance_enabled')->default(false)->after('has_time_module');
            $table->string('presence_compliance_scope', 32)->nullable()->after('presence_compliance_enabled');
            $table->text('presence_rsz_client_id')->nullable()->after('presence_compliance_scope');
            $table->text('presence_rsz_private_key')->nullable()->after('presence_rsz_client_id');
        });

        Schema::table('workers', function (Blueprint $table) {
            $table->text('ssin')->nullable()->after('phone');
        });

        Schema::table('locations', function (Blueprint $table) {
            $table->string('contractual_relationship_reference', 13)->nullable()->after('notes');
            $table->decimal('latitude', 10, 8)->nullable()->after('contractual_relationship_reference');
            $table->decimal('longitude', 11, 8)->nullable()->after('latitude');
        });

        Schema::create('presence_submissions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('worker_id')->constrained()->cascadeOnDelete();
            $table->foreignId('work_shift_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('work_break_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('clock_point_id')->nullable()->constrained('clock_points')->nullOnDelete();
            $table->foreignId('location_id')->nullable()->constrained()->nullOnDelete();
            $table->string('source_event', 32);
            $table->string('presence_type', 8);
            $table->string('scope', 32);
            $table->timestamp('registration_at');
            $table->string('status', 32);
            $table->unsignedBigInteger('rsz_id')->nullable();
            $table->string('rsz_validity', 32)->nullable();
            $table->json('remarks')->nullable();
            $table->json('request_meta')->nullable();
            $table->text('error_message')->nullable();
            $table->timestamp('submitted_at')->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'status']);
            $table->index(['tenant_id', 'registration_at']);
            $table->index(['work_shift_id', 'source_event']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('presence_submissions');

        Schema::table('locations', function (Blueprint $table) {
            $table->dropColumn(['contractual_relationship_reference', 'latitude', 'longitude']);
        });

        Schema::table('workers', function (Blueprint $table) {
            $table->dropColumn('ssin');
        });

        Schema::table('tenants', function (Blueprint $table) {
            $table->dropColumn([
                'enterprise_number',
                'foreign_vat_number',
                'presence_compliance_enabled',
                'presence_compliance_scope',
                'presence_rsz_client_id',
                'presence_rsz_private_key',
            ]);
        });
    }
};
