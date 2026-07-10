<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            $table->unsignedSmallInteger('time_qr_rotation_months')->nullable()->after('has_esg_module');
        });

        Schema::table('clock_points', function (Blueprint $table) {
            $table->timestamp('qr_renewed_at')->nullable()->after('qr_token');
            $table->timestamp('qr_renewal_recommended_at')->nullable()->after('qr_renewed_at');
        });

        Schema::create('clock_point_qr_tokens', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('clock_point_id')->constrained()->cascadeOnDelete();
            $table->string('qr_token', 64)->unique();
            $table->timestamp('grace_ends_at');
            $table->timestamp('blocked_at')->nullable();
            $table->timestamps();

            $table->index(['clock_point_id', 'blocked_at']);
        });

        DB::table('clock_points')->orderBy('id')->get()->each(function (object $row): void {
            DB::table('clock_points')->where('id', $row->id)->update([
                'qr_renewed_at' => $row->created_at ?? now(),
            ]);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('clock_point_qr_tokens');

        Schema::table('clock_points', function (Blueprint $table) {
            $table->dropColumn(['qr_renewed_at', 'qr_renewal_recommended_at']);
        });

        Schema::table('tenants', function (Blueprint $table) {
            $table->dropColumn('time_qr_rotation_months');
        });
    }
};
