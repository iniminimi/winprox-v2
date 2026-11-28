<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('screens', function (Blueprint $table) {
            $table->string('display_token_hash', 64)->nullable()->after('display_secret');
            $table->string('display_token_prefix', 12)->nullable()->after('display_token_hash');
            $table->index('display_token_hash');
        });

        Schema::table('screen_claims', function (Blueprint $table) {
            $table->text('device_token')->nullable()->after('issued_token');
        });
    }

    public function down(): void
    {
        Schema::table('screen_claims', function (Blueprint $table) {
            $table->dropColumn('device_token');
        });

        Schema::table('screens', function (Blueprint $table) {
            $table->dropIndex(['display_token_hash']);
            $table->dropColumn(['display_token_hash', 'display_token_prefix']);
        });
    }
};
