<?php

namespace App\Actions\TenantPurge;

use App\Enums\TenantPurgeStatus;
use App\Enums\TenantPurgeTrack;
use App\Models\TenantPurgeRequest;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

/**
 * Voert geplande expired-trial purges uit waarvan scheduled_purge_at is verstreken.
 */
final class ExecuteDueExpiredTrialPurgesAction
{
    public function __construct(private ExecuteTenantPurgeAction $execute) {}

    /**
     * @return array{scanned: int, executed: int, failed: int}
     */
    public function handle(?Carbon $now = null): array
    {
        $now ??= now();
        $stats = ['scanned' => 0, 'executed' => 0, 'failed' => 0];

        TenantPurgeRequest::query()
            ->where('track', TenantPurgeTrack::ExpiredTrial)
            ->where('status', TenantPurgeStatus::Scheduled)
            ->whereNotNull('tenant_id')
            ->whereNotNull('scheduled_purge_at')
            ->where('scheduled_purge_at', '<=', $now)
            ->orderBy('id')
            ->each(function (TenantPurgeRequest $request) use (&$stats): void {
                $stats['scanned']++;

                try {
                    $this->execute->handle($request, null, null);
                    $stats['executed']++;
                } catch (ValidationException $e) {
                    $stats['failed']++;
                    Log::warning('Expired trial purge skipped', [
                        'purge_request_id' => $request->id,
                        'tenant_id' => $request->tenant_id,
                        'errors' => $e->errors(),
                    ]);
                } catch (\Throwable $e) {
                    $stats['failed']++;
                    Log::error('Expired trial purge failed', [
                        'purge_request_id' => $request->id,
                        'tenant_id' => $request->tenant_id,
                        'message' => $e->getMessage(),
                    ]);
                }
            });

        return $stats;
    }
}
