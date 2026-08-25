<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('issues', function (Blueprint $table) {
            $table->boolean('reporter_email_verified')->default(false)->after('reporter_contact');
        });

        if (Schema::hasTable('qr_report_email_holds')) {
            $ids = DB::table('qr_report_email_holds')
                ->whereNotNull('confirmed_at')
                ->whereNotNull('issue_id')
                ->pluck('issue_id');

            if ($ids->isNotEmpty()) {
                DB::table('issues')
                    ->whereIn('id', $ids->all())
                    ->update(['reporter_email_verified' => true]);
            }
        }
    }

    public function down(): void
    {
        Schema::table('issues', function (Blueprint $table) {
            $table->dropColumn('reporter_email_verified');
        });
    }
};
