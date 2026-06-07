<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('workers', function (Blueprint $table) {
            $table->string('import_batch_id')->nullable()->index()->after('is_teamleader');
            $table->string('email')->nullable()->after('import_batch_id');
            $table->string('phone', 64)->nullable()->after('email');
            $table->string('external_id')->nullable()->index()->after('phone');
        });
    }

    public function down(): void
    {
        Schema::table('workers', function (Blueprint $table) {
            $table->dropIndex(['import_batch_id']);
            $table->dropIndex(['external_id']);
            $table->dropColumn(['import_batch_id', 'email', 'phone', 'external_id']);
        });
    }
};
