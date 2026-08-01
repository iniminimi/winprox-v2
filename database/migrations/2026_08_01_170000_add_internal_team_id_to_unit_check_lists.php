<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('unit_check_lists', function (Blueprint $table) {
            $table->foreignId('internal_team_id')
                ->nullable()
                ->after('tenant_id')
                ->constrained('internal_teams')
                ->nullOnDelete();
            $table->index(['tenant_id', 'internal_team_id', 'is_active'], 'unit_check_lists_tenant_team_active_idx');
        });
    }

    public function down(): void
    {
        Schema::table('unit_check_lists', function (Blueprint $table) {
            $table->dropIndex('unit_check_lists_tenant_team_active_idx');
            $table->dropConstrainedForeignId('internal_team_id');
        });
    }
};
