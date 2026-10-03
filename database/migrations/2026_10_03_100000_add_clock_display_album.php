<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('clock_points', function (Blueprint $table) {
            // Album-vensters: tijdens deze uren toont het scherm een
            // foto-slideshow i.p.v. de QR-klok (max 2 vensters per punt).
            $table->time('album1_from')->nullable()->after('display_on_until');
            $table->time('album1_until')->nullable()->after('album1_from');
            $table->time('album2_from')->nullable()->after('album1_until');
            $table->time('album2_until')->nullable()->after('album2_from');
        });

        Schema::create('clock_display_images', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            // NULL = "toon op alle clockpoints" (globaal binnen de tenant).
            $table->foreignId('clock_point_id')->nullable()->constrained('clock_points')->cascadeOnDelete();
            $table->string('path');
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index(['tenant_id', 'clock_point_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('clock_display_images');

        Schema::table('clock_points', function (Blueprint $table) {
            $table->dropColumn(['album1_from', 'album1_until', 'album2_from', 'album2_until']);
        });
    }
};
