<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('locations', function (Blueprint $table) {
            $table->string('street')->nullable()->after('name');
            $table->string('house_number', 32)->nullable()->after('street');
            $table->string('postal_code', 16)->nullable()->after('house_number');
            $table->string('city')->nullable()->after('postal_code');
            $table->char('country_code', 2)->default('BE')->after('city');
            $table->text('notes')->nullable()->after('country_code');
            $table->string('location_qr_token', 64)->nullable()->unique()->after('notes');
        });

        Schema::create('unit_bulk_batches', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('location_id')->constrained()->cascadeOnDelete();
            $table->string('prefix')->nullable();
            $table->string('scheme', 32);
            $table->unsignedSmallInteger('floors');
            $table->unsignedSmallInteger('rooms_per_floor');
            $table->foreignId('internal_team_id')->nullable()->constrained('internal_teams')->nullOnDelete();
            $table->unsignedInteger('units_count');
            $table->timestamps();

            $table->index(['tenant_id', 'location_id', 'created_at']);
        });

        Schema::table('units', function (Blueprint $table) {
            $table->foreignId('bulk_batch_id')->nullable()->after('qr_token')
                ->constrained('unit_bulk_batches')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('units', function (Blueprint $table) {
            $table->dropConstrainedForeignId('bulk_batch_id');
        });

        Schema::dropIfExists('unit_bulk_batches');

        Schema::table('locations', function (Blueprint $table) {
            $table->dropUnique(['location_qr_token']);
            $table->dropColumn([
                'street',
                'house_number',
                'postal_code',
                'city',
                'country_code',
                'notes',
                'location_qr_token',
            ]);
        });
    }
};
