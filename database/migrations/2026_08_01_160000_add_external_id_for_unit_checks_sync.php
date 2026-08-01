<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('units', function (Blueprint $table) {
            $table->string('external_id', 100)->nullable()->after('unit_check_list_id');
            $table->unique(['tenant_id', 'external_id'], 'units_tenant_external_unique');
        });

        Schema::table('unit_checks', function (Blueprint $table) {
            $table->string('external_id', 100)->nullable()->after('checklist_items');
            $table->unique(['tenant_id', 'external_id'], 'unit_checks_tenant_external_unique');
        });
    }

    public function down(): void
    {
        Schema::table('unit_checks', function (Blueprint $table) {
            $table->dropUnique('unit_checks_tenant_external_unique');
            $table->dropColumn('external_id');
        });

        Schema::table('units', function (Blueprint $table) {
            $table->dropUnique('units_tenant_external_unique');
            $table->dropColumn('external_id');
        });
    }
};
