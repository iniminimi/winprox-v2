<?php

declare(strict_types=1);

namespace App\Actions\Marketing;

use App\Actions\Audit\LogAuditAction;
use App\Jobs\SendPromoCampaignEmailJob;
use App\Models\PromoCampaign;
use Illuminate\Bus\UniqueLock;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class PausePromoCampaignSendingAction
{
    public function __construct(private LogAuditAction $logAudit) {}

    /**
     * Stop bulk promo sending: mark campaign(s) paused and drop waiting jobs.
     *
     * @return array{paused_campaigns: int, purged_jobs: int}
     */
    public function handle(?PromoCampaign $campaign, ?int $actorUserId, string $reason = 'manual'): array
    {
        $query = PromoCampaign::query();
        if ($campaign !== null) {
            $query->whereKey($campaign->id);
        }

        $pausedIds = $query->pluck('id')->map(fn ($id): int => (int) $id)->all();

        if ($pausedIds !== []) {
            PromoCampaign::query()
                ->whereIn('id', $pausedIds)
                ->whereNull('emails_paused_at')
                ->update(['emails_paused_at' => now()]);
        }

        $purgedJobs = $this->purgeQueuedJobs(
            campaignId: $campaign !== null ? (int) $campaign->id : null,
            includeMunicipal: $campaign === null,
        );

        $this->logAudit->handle(
            userId: $actorUserId,
            tenantId: null,
            action: 'marketing.promo_campaign_emails_paused',
            modelType: $campaign !== null ? 'PromoCampaign' : null,
            modelId: $campaign?->id,
            payload: [
                'campaign_ids' => $pausedIds,
                'purged_jobs' => $purgedJobs,
                'all' => $campaign === null,
                'reason' => $reason,
            ],
        );

        return [
            'paused_campaigns' => count($pausedIds),
            'purged_jobs' => $purgedJobs,
        ];
    }

    private function purgeQueuedJobs(?int $campaignId, bool $includeMunicipal): int
    {
        $deleted = 0;
        $uniqueLock = new UniqueLock(Cache::store());

        foreach (['jobs', 'failed_jobs'] as $table) {
            if (! Schema::hasTable($table)) {
                continue;
            }

            $rows = DB::table($table)
                ->where(function ($query) use ($includeMunicipal): void {
                    $query->where('payload', 'like', '%SendPromoCampaignEmailJob%');
                    if ($includeMunicipal) {
                        $query->orWhere('payload', 'like', '%SendMunicipalPromoLetterEmailJob%');
                    }
                })
                ->get(['id', 'payload']);

            $idsToDelete = [];
            foreach ($rows as $row) {
                $payload = (string) $row->payload;
                $jobCampaignId = $this->intFromSerializedPayload($payload, 'promoCampaignId');
                $jobTargetId = $this->intFromSerializedPayload($payload, 'promoCampaignTargetId');

                $isCampaignJob = str_contains($payload, 'SendPromoCampaignEmailJob');
                if ($isCampaignJob && $campaignId !== null && $jobCampaignId !== $campaignId) {
                    continue;
                }

                if ($isCampaignJob && $jobCampaignId !== null && $jobTargetId !== null) {
                    $uniqueLock->release(new SendPromoCampaignEmailJob(
                        promoCampaignId: $jobCampaignId,
                        promoCampaignTargetId: $jobTargetId,
                        actorUserId: 0,
                    ));
                }

                $idsToDelete[] = (int) $row->id;
            }

            if ($idsToDelete === []) {
                continue;
            }

            $deleted += DB::table($table)->whereIn('id', $idsToDelete)->delete();
        }

        return $deleted;
    }

    private function intFromSerializedPayload(string $payload, string $property): ?int
    {
        $command = $payload;
        $decoded = json_decode($payload, true);
        if (is_array($decoded)) {
            $command = (string) ($decoded['data']['command'] ?? $payload);
        }

        if (preg_match('/'.$property.'";i:(\d+);/', $command, $matches) !== 1) {
            return null;
        }

        return (int) $matches[1];
    }
}
