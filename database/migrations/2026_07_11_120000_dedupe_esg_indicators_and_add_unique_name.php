<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $duplicateGroups = DB::table('esg_indicators')
            ->select('tenant_id', 'name')
            ->groupBy('tenant_id', 'name')
            ->havingRaw('COUNT(*) > 1')
            ->get();

        foreach ($duplicateGroups as $group) {
            $ids = DB::table('esg_indicators')
                ->where('tenant_id', $group->tenant_id)
                ->where('name', $group->name)
                ->orderBy('id')
                ->pluck('id')
                ->all();

            $keepId = array_shift($ids);

            foreach ($ids as $duplicateId) {
                DB::table('esg_measurements')
                    ->where('esg_indicator_id', $duplicateId)
                    ->update(['esg_indicator_id' => $keepId]);

                DB::table('issues')
                    ->where('esg_indicator_id', $duplicateId)
                    ->update(['esg_indicator_id' => $keepId]);

                DB::table('esg_indicator_translations')
                    ->where('esg_indicator_id', $duplicateId)
                    ->delete();

                DB::table('esg_indicators')
                    ->where('id', $duplicateId)
                    ->delete();
            }
        }

        Schema::table('esg_indicators', function (Blueprint $table) {
            $table->unique(['tenant_id', 'name'], 'esg_indicators_tenant_name_unique');
        });
    }

    public function down(): void
    {
        Schema::table('esg_indicators', function (Blueprint $table) {
            $table->dropUnique('esg_indicators_tenant_name_unique');
        });
    }
};
