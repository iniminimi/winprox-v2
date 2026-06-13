<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('geoname_places', function (Blueprint $table) {
            $table->unsignedBigInteger('id')->primary();
            $table->string('name', 200);
            $table->decimal('latitude', 10, 8);
            $table->decimal('longitude', 11, 8);
            $table->char('country_code', 2);
            $table->char('feature_class', 1);
            $table->string('feature_code', 10);

            $table->index(['latitude', 'longitude']);
            $table->index('country_code');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('geoname_places');
    }
};
