<?php

use Carbon\Carbon;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $defaultMonths = (int) config('time.qr_rotation_months_default', 6);
        $monthsByTenant = DB::table('tenants')->pluck('time_qr_rotation_months', 'id');

        DB::table('clock_points')
            ->whereNotNull('qr_renewal_recommended_at')
            ->orderBy('id')
            ->each(function (object $row) use ($defaultMonths, $monthsByTenant): void {
                $months = $monthsByTenant[$row->tenant_id] ?? null;
                $months = $months === null ? $defaultMonths : (int) $months;

                if ($months <= 0) {
                    DB::table('clock_points')->where('id', $row->id)
                        ->update(['qr_renewal_recommended_at' => null]);

                    return;
                }

                $renewedAt = Carbon::parse($row->qr_renewed_at ?? $row->created_at ?? now());
                $earliest = $renewedAt->copy()->addDays(max(1, $months * 30));
                $latest = $earliest->copy()->addDays(14);
                $current = Carbon::parse($row->qr_renewal_recommended_at);

                if ($current->lt($earliest) || $current->gt($latest)) {
                    DB::table('clock_points')->where('id', $row->id)->update([
                        'qr_renewal_recommended_at' => $earliest->copy()->addDays(random_int(0, 14)),
                    ]);
                }
            });
    }

    public function down(): void
    {
        // Herstel van willekeurige aanbevelingsdatums is niet terugdraaibaar.
    }
};
