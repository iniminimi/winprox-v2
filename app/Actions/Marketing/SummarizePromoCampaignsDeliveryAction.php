<?php

declare(strict_types=1);

namespace App\Actions\Marketing;

use App\Data\Marketing\PromoCampaignDeliverySummaryData;
use App\Enums\MunicipalPromoEmailSendStatus;
use App\Models\PromoCampaign;
use App\Models\PromoCampaignEmailSend;
use App\Models\PromoCampaignTarget;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class SummarizePromoCampaignsDeliveryAction
{
    /**
     * @param  Collection<int, PromoCampaign>|iterable<PromoCampaign>  $campaigns
     * @return array<int, PromoCampaignDeliverySummaryData>
     */
    public function handle(iterable $campaigns): array
    {
        $campaigns = Collection::make($campaigns)->values();
        if ($campaigns->isEmpty()) {
            return [];
        }

        $ids = $campaigns->pluck('id')->map(fn ($id): int => (int) $id)->all();

        $targetTotals = PromoCampaignTarget::query()
            ->whereIn('promo_campaign_id', $ids)
            ->selectRaw('promo_campaign_id, COUNT(*) as aggregate')
            ->groupBy('promo_campaign_id')
            ->pluck('aggregate', 'promo_campaign_id');

        $withEmailTotals = PromoCampaignTarget::query()
            ->whereIn('promo_campaign_id', $ids)
            ->whereNotNull('email')
            ->where('email', '!=', '')
            ->selectRaw('promo_campaign_id, COUNT(*) as aggregate')
            ->groupBy('promo_campaign_id')
            ->pluck('aggregate', 'promo_campaign_id');

        $bouncedTotals = PromoCampaignTarget::query()
            ->whereIn('promo_campaign_id', $ids)
            ->where('undelivered', true)
            ->selectRaw('promo_campaign_id, COUNT(*) as aggregate')
            ->groupBy('promo_campaign_id')
            ->pluck('aggregate', 'promo_campaign_id');

        $sendRows = PromoCampaignEmailSend::query()
            ->whereIn('promo_campaign_id', $ids)
            ->selectRaw('promo_campaign_id, status, COUNT(*) as aggregate')
            ->groupBy('promo_campaign_id', 'status')
            ->get();

        /** @var array<int, array<string, int>> $sendsByCampaign */
        $sendsByCampaign = [];
        foreach ($sendRows as $row) {
            $campaignId = (int) $row->promo_campaign_id;
            $status = $row->status instanceof \BackedEnum
                ? $row->status->value
                : (string) $row->status;
            $sendsByCampaign[$campaignId][$status] = (int) $row->aggregate;
        }

        $queuedJobsByCampaign = $this->countJobsByCampaign($ids, 'jobs');

        $summaries = [];

        foreach ($campaigns as $campaign) {
            $campaignId = (int) $campaign->id;
            $statusCounts = $sendsByCampaign[$campaignId] ?? [];
            $sent = $statusCounts[MunicipalPromoEmailSendStatus::Sent->value] ?? 0;
            $failed = $statusCounts[MunicipalPromoEmailSendStatus::Failed->value] ?? 0;
            $skipped = $statusCounts[MunicipalPromoEmailSendStatus::Skipped->value] ?? 0;
            $remaining = $this->countRemaining($campaign);
            $queuedJobs = $queuedJobsByCampaign[$campaignId] ?? 0;
            $targets = (int) ($targetTotals[$campaignId] ?? 0);
            $withEmail = (int) ($withEmailTotals[$campaignId] ?? 0);
            $bounced = (int) ($bouncedTotals[$campaignId] ?? 0);

            $summaries[$campaignId] = new PromoCampaignDeliverySummaryData(
                targets: $targets,
                withEmail: $withEmail,
                sent: $sent,
                failed: $failed,
                skipped: $skipped,
                bounced: $bounced,
                remaining: $remaining,
                queuedJobs: $queuedJobs,
                status: $this->resolveStatus(
                    withEmail: $withEmail,
                    sent: $sent,
                    failed: $failed,
                    remaining: $remaining,
                    queuedJobs: $queuedJobs,
                ),
            );
        }

        return $summaries;
    }

    private function countRemaining(PromoCampaign $campaign): int
    {
        $sentOrBouncedIds = PromoCampaignEmailSend::query()
            ->where('promo_campaign_id', $campaign->id)
            ->whereIn('status', [
                MunicipalPromoEmailSendStatus::Sent,
                MunicipalPromoEmailSendStatus::Bounced,
            ])
            ->pluck('promo_campaign_target_id');

        $query = PromoCampaignTarget::query()
            ->where('promo_campaign_id', $campaign->id)
            ->where('undelivered', false)
            ->whereNotNull('email')
            ->where('email', '!=', '');

        if ($sentOrBouncedIds->isNotEmpty()) {
            $query->whereNotIn('id', $sentOrBouncedIds);
        }

        if ($campaign->attach_letter_to_email) {
            $query->whereNotNull('generated_at')->whereNotNull('docx_filename');
        }

        $query->whereNotExists(function ($sub): void {
            $sub->selectRaw('1')
                ->from('email_unsubscribes')
                ->whereRaw('email_unsubscribes.email = LOWER(TRIM(promo_campaign_targets.email))');
        });

        return $query->count();
    }

    /**
     * @param  list<int>  $campaignIds
     * @return array<int, int>
     */
    private function countJobsByCampaign(array $campaignIds, string $table): array
    {
        $counts = array_fill_keys($campaignIds, 0);

        if (! Schema::hasTable($table)) {
            return $counts;
        }

        $rows = DB::table($table)
            ->where('payload', 'like', '%SendPromoCampaignEmailJob%')
            ->pluck('payload');

        foreach ($rows as $payload) {
            $campaignId = $this->campaignIdFromJobPayload((string) $payload);
            if ($campaignId === null || ! array_key_exists($campaignId, $counts)) {
                continue;
            }

            $counts[$campaignId]++;
        }

        return $counts;
    }

    private function campaignIdFromJobPayload(string $payload): ?int
    {
        $command = $payload;
        $decoded = json_decode($payload, true);
        if (is_array($decoded)) {
            $command = (string) ($decoded['data']['command'] ?? $payload);
        }

        if (preg_match('/promoCampaignId";i:(\d+);/', $command, $matches) !== 1) {
            return null;
        }

        return (int) $matches[1];
    }

    private function resolveStatus(
        int $withEmail,
        int $sent,
        int $failed,
        int $remaining,
        int $queuedJobs,
    ): string {
        if ($withEmail === 0) {
            return 'no_recipients';
        }

        if ($queuedJobs > 0 && $remaining > 0) {
            return 'sending';
        }

        if ($remaining === 0) {
            return 'complete';
        }

        if ($sent > 0 || $failed > 0) {
            return 'needs_restart';
        }

        return 'not_started';
    }
}
