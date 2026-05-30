<?php

namespace App\Actions\Retention;

use App\Models\Issue;
use App\Models\IssuePhoto;
use App\Models\Tenant;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;

/**
 * Verwijdert meldingen (incl. media) van tenants die lang inactief zijn.
 */
final class PruneInactiveTenantFacilityDataAction
{
    /**
     * @return array{tenants_scanned: int, issues_removed: int, photos_removed: int}
     */
    public function handle(bool $dryRun = false, ?Carbon $now = null): array
    {
        $now ??= now();
        $cutoff = $now->copy()->subDays((int) config('data_retention.inactive_tenant_days', 730));

        $stats = ['tenants_scanned' => 0, 'issues_removed' => 0, 'photos_removed' => 0];

        $this->eligibleTenantsQuery($cutoff)
            ->orderBy('id')
            ->each(function (Tenant $tenant) use ($dryRun, &$stats): void {
                $stats['tenants_scanned']++;

                Issue::query()
                    ->withoutGlobalScopes()
                    ->where('tenant_id', $tenant->id)
                    ->orderBy('id')
                    ->chunkById(50, function ($issues) use ($dryRun, &$stats): void {
                        foreach ($issues as $issue) {
                            $photos = IssuePhoto::query()
                                ->withoutGlobalScopes()
                                ->where('issue_id', $issue->id)
                                ->get();

                            foreach ($photos as $photo) {
                                if (! $dryRun) {
                                    Storage::disk('public')->delete($photo->path);
                                }
                                $stats['photos_removed']++;
                            }

                            if (! $dryRun) {
                                $issue->delete();
                            }
                            $stats['issues_removed']++;
                        }
                    });
            });

        return $stats;
    }

    /**
     * @return \Illuminate\Database\Eloquent\Builder<Tenant>
     */
    private function eligibleTenantsQuery(Carbon $cutoff): \Illuminate\Database\Eloquent\Builder
    {
        return Tenant::query()
            ->where('is_active', false)
            ->where(function ($query) use ($cutoff): void {
                $query->where(function ($q) use ($cutoff): void {
                    $q->whereNotNull('billing_active_until')
                        ->where('billing_active_until', '<', $cutoff);
                })->orWhere(function ($q) use ($cutoff): void {
                    $q->whereNull('billing_active_until')
                        ->whereNotNull('trial_ends_at')
                        ->where('trial_ends_at', '<', $cutoff);
                });
            });
    }
}
