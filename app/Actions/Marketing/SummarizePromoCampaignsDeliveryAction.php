<?php

declare(strict_types=1);

namespace App\Actions\Marketing;

use App\Data\Marketing\PromoCampaignDeliverySummaryData;
use App\Enums\MunicipalPromoEmailSendStatus;
use App\Enums\PromoBounceKind;
use App\Models\PromoCampaign;
use App\Models\PromoCampaignEmailSend;
use App\Models\PromoCampaignTarget;
use App\Support\Marketing\PromoBounceMessageParser;
use Illuminate\Support\Carbon;
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

        $bounceKindTotals = $this->countBounceKindsByCampaign($ids);

        $sendRows = PromoCampaignEmailSend::query()
            ->whereIn('promo_campaign_id', $ids)
            ->selectRaw('promo_campaign_id, status, COUNT(*) as aggregate')
            ->groupBy('promo_campaign_id', 'status')
            ->get();

        $sentAtRows = PromoCampaignEmailSend::query()
            ->whereIn('promo_campaign_id', $ids)
            ->where('status', MunicipalPromoEmailSendStatus::Sent)
            ->whereNotNull('sent_at')
            ->selectRaw('promo_campaign_id, MIN(sent_at) as first_sent_at, MAX(sent_at) as last_sent_at')
            ->groupBy('promo_campaign_id')
            ->get()
            ->keyBy(fn ($row): int => (int) $row->promo_campaign_id);

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
            $kinds = $bounceKindTotals[$campaignId] ?? [
                PromoBounceKind::Unknown->value => 0,
                PromoBounceKind::Blacklist->value => 0,
                PromoBounceKind::MailboxFull->value => 0,
                PromoBounceKind::Spam->value => 0,
                PromoBounceKind::DomainBlock->value => 0,
            ];

            $sentAt = $sentAtRows->get($campaignId);
            $attempted = $sent + $bounced;

            $summaries[$campaignId] = new PromoCampaignDeliverySummaryData(
                targets: $targets,
                withEmail: $withEmail,
                sent: $sent,
                failed: $failed,
                skipped: $skipped,
                bounced: $bounced,
                bouncePercent: $attempted > 0 ? (int) round(100 * $bounced / $attempted) : 0,
                bounceUnknown: (int) ($kinds[PromoBounceKind::Unknown->value] ?? 0),
                bounceBlacklist: (int) ($kinds[PromoBounceKind::Blacklist->value] ?? 0),
                bounceMailboxFull: (int) ($kinds[PromoBounceKind::MailboxFull->value] ?? 0),
                bounceSpam: (int) ($kinds[PromoBounceKind::Spam->value] ?? 0),
                bounceDomainBlock: (int) ($kinds[PromoBounceKind::DomainBlock->value] ?? 0),
                remaining: $remaining,
                queuedJobs: $queuedJobs,
                status: $this->resolveStatus(
                    campaign: $campaign,
                    withEmail: $withEmail,
                    sent: $sent,
                    failed: $failed,
                    remaining: $remaining,
                    queuedJobs: $queuedJobs,
                ),
                firstSentAt: $this->parseSentAt($sentAt?->first_sent_at ?? null),
                lastSentAt: $this->parseSentAt($sentAt?->last_sent_at ?? null),
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

        $query->whereNotExists(function ($sub): void {
            $sub->selectRaw('1')
                ->from('email_unsubscribes')
                ->whereRaw('email_unsubscribes.email = LOWER(TRIM(promo_campaign_targets.email))');
        });

        return $query->count();
    }

    /**
     * @param  list<int>  $campaignIds
     * @return array<int, array<string, int>>
     */
    private function countBounceKindsByCampaign(array $campaignIds): array
    {
        $empty = [
            PromoBounceKind::Unknown->value => 0,
            PromoBounceKind::Blacklist->value => 0,
            PromoBounceKind::MailboxFull->value => 0,
            PromoBounceKind::Spam->value => 0,
            PromoBounceKind::DomainBlock->value => 0,
        ];
        $counts = [];
        foreach ($campaignIds as $id) {
            $counts[$id] = $empty;
        }

        $rows = PromoCampaignEmailSend::query()
            ->whereIn('promo_campaign_id', $campaignIds)
            ->where('status', MunicipalPromoEmailSendStatus::Bounced)
            ->get(['promo_campaign_id', 'error_message']);

        foreach ($rows as $row) {
            $kind = PromoBounceKind::fromStoredReason($row->error_message);
            if ($kind === PromoBounceKind::Other || $kind === PromoBounceKind::Blacklist) {
                $classified = PromoBounceMessageParser::classify((string) $row->error_message);
                if ($classified === PromoBounceKind::DomainBlock || $kind === PromoBounceKind::Other) {
                    $kind = $classified;
                }
            }
            if ($kind === PromoBounceKind::Other) {
                continue;
            }

            $campaignId = (int) $row->promo_campaign_id;
            if (! isset($counts[$campaignId])) {
                $counts[$campaignId] = $empty;
            }

            $counts[$campaignId][$kind->value]++;
        }

        return $counts;
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
        PromoCampaign $campaign,
        int $withEmail,
        int $sent,
        int $failed,
        int $remaining,
        int $queuedJobs,
    ): string {
        if ($withEmail === 0) {
            return 'no_recipients';
        }

        if ($remaining === 0 && $queuedJobs === 0) {
            return 'complete';
        }

        if ($campaign->isEmailSendingPaused()) {
            return 'paused';
        }

        if ($queuedJobs > 0 && $remaining > 0) {
            return 'sending';
        }

        if ($sent > 0 || $failed > 0) {
            return 'needs_restart';
        }

        return 'not_started';
    }

    private function parseSentAt(mixed $value): ?Carbon
    {
        if ($value === null || $value === '') {
            return null;
        }

        return Carbon::parse($value);
    }
}
