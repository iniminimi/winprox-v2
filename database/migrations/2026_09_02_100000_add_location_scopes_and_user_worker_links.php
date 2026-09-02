<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('internal_teams', function (Blueprint $table) {
            $table->boolean('clocks_all_locations')->default(false)->after('is_active');
        });

        Schema::table('workers', function (Blueprint $table) {
            $table->foreignId('user_id')->nullable()->after('tenant_id')->constrained()->nullOnDelete();
            $table->unique(['tenant_id', 'user_id']);
        });

        Schema::create('location_user', function (Blueprint $table) {
            $table->id();
            $table->foreignId('location_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['location_id', 'user_id']);
        });

        Schema::create('location_worker', function (Blueprint $table) {
            $table->id();
            $table->foreignId('location_id')->constrained()->cascadeOnDelete();
            $table->foreignId('worker_id')->constrained()->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['location_id', 'worker_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('location_worker');
        Schema::dropIfExists('location_user');
        Schema::table('workers', function (Blueprint $table) {
            $table->dropConstrainedForeignId('user_id');
        });
        Schema::table('internal_teams', function (Blueprint $table) {
            $table->dropColumn('clocks_all_locations');
        });
    }
};
