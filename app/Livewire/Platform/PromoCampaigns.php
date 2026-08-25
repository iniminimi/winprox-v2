<?php

namespace App\Livewire\Platform;

use App\Actions\Marketing\CopyPromoCampaignAction;
use App\Actions\Marketing\CreatePromoCampaignAction;
use App\Enums\PromoLanding;
use App\Actions\Marketing\DeletePromoCampaignAction;
use App\Actions\Marketing\PausePromoCampaignSendingAction;
use App\Actions\Marketing\ProcessPromoMailboxBouncesAction;
use App\Actions\Marketing\ResumePromoCampaignSendingAction;
use App\Actions\Marketing\SummarizePromoCampaignsDeliveryAction;
use App\Http\Requests\Marketing\CopyPromoCampaignRequest;
use App\Http\Requests\Marketing\CreatePromoCampaignRequest;
use App\Jobs\ProcessPromoMailboxBouncesJob;
use App\Models\PromoCampaign;
use App\Models\User;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Throwable;

#[Layout('components.layouts.app')]
#[Title('WinProx')]
class PromoCampaigns extends Component
{
    use AuthorizesRequests;

    public string $slug = '';

    public string $name = '';

    public string $locale = 'nl';

    public string $landing = 'government';

    public ?string $flashMessage = null;

    public string $flashType = 'success';

    public bool $showCopyModal = false;

    public ?int $copyFromCampaignId = null;

    public string $copySlug = '';

    public string $copyName = '';

    public string $copyLocale = 'nl';

    public bool $showPauseConfirm = false;

    public bool $showDeleteConfirm = false;

    public ?int $deleteCampaignId = null;

    public bool $bounceScanQueued = false;

    public function mount(): void
    {
        $this->authorize('managePromoCampaigns', User::class);
    }

    public function createCampaign(CreatePromoCampaignAction $create): void
    {
        $this->authorize('managePromoCampaigns', User::class);

        $this->slug = strtolower(trim($this->slug));
        $this->locale = strtolower(trim($this->locale));

        $validated = $this->validate(
            CreatePromoCampaignRequest::ruleSet(),
            CreatePromoCampaignRequest::validationMessages(),
        );
        $user = auth()->user();
        if ($user === null) {
            return;
        }

        $campaign = $create->handle(
            slug: $validated['slug'],
            name: $validated['name'],
            locale: $validated['locale'],
            actorUserId: (int) $user->id,
            landing: PromoLanding::from($validated['landing']),
        );

        $this->reset(['slug', 'name']);
        $this->locale = 'nl';
        $this->landing = PromoLanding::default()->value;

        $this->redirect(route('platform.promo-campaigns.edit', $campaign), navigate: true);
    }

    public function openCopyModal(int $campaignId): void
    {
        $this->authorize('managePromoCampaigns', User::class);

        $source = PromoCampaign::query()->findOrFail($campaignId);

        $this->copyFromCampaignId = $source->id;
        $this->copySlug = '';
        $this->copyName = __('platform.promo_campaigns.copy_name_default', ['source' => $source->name]);
        $this->copyLocale = $source->locale;
        $this->showDeleteConfirm = false;
        $this->deleteCampaignId = null;
        $this->showCopyModal = true;
        $this->resetValidation();
    }

    public function closeCopyModal(): void
    {
        $this->showCopyModal = false;
        $this->copyFromCampaignId = null;
        $this->reset(['copySlug', 'copyName']);
        $this->copyLocale = 'nl';
        $this->resetValidation();
    }

    public function copyCampaign(CopyPromoCampaignAction $copy): void
    {
        $this->authorize('managePromoCampaigns', User::class);

        $this->copySlug = strtolower(trim($this->copySlug));
        $this->copyLocale = strtolower(trim($this->copyLocale));

        $validated = $this->validate(
            CopyPromoCampaignRequest::ruleSet(),
            CopyPromoCampaignRequest::validationMessages(),
        );

        $user = auth()->user();
        if ($user === null) {
            return;
        }

        $source = PromoCampaign::query()->findOrFail((int) $validated['copyFromCampaignId']);

        $campaign = $copy->handle(
            source: $source,
            slug: $validated['copySlug'],
            name: $validated['copyName'],
            locale: $validated['copyLocale'],
            actorUserId: (int) $user->id,
        );

        $this->closeCopyModal();

        $this->redirect(route('platform.promo-campaigns.edit', $campaign), navigate: true);
    }

    public function processPromoBounces(ProcessPromoMailboxBouncesAction $process): void
    {
        $this->authorize('managePromoCampaigns', User::class);

        if (config('queue.default') !== 'sync') {
            ProcessPromoMailboxBouncesJob::remember([
                'status' => 'queued',
                'at' => now()->toIso8601String(),
            ]);
            ProcessPromoMailboxBouncesJob::dispatch();
            $this->bounceScanQueued = true;
            $this->flashType = 'success';
            $this->flashMessage = __('platform.promo_campaigns.bounces_queued');

            return;
        }

        $this->applyBounceScanResult($process);
    }

    private function applyBounceScanResult(ProcessPromoMailboxBouncesAction $process): void
    {
        try {
            $result = $process->handle(
                unseenOnly: false,
                limit: ProcessPromoMailboxBouncesAction::DEFAULT_MANUAL_LIMIT,
                dryRun: false,
                sinceDays: ProcessPromoMailboxBouncesAction::DEFAULT_SINCE_DAYS,
            );
        } catch (Throwable $e) {
            $this->flashType = 'error';
            $message = trim($e->getMessage());
            $this->flashMessage = $message !== ''
                ? __('platform.promo_campaigns.bounces_failed', ['error' => $message])
                : __('platform.promo_campaigns.bounces_failed_generic');

            return;
        }

        $this->flashFromBounceResult($result);
    }

    /**
     * @param  array{scanned: int, bounce_messages: int, emails_found: int, removed: int, blocked: int, dry_run?: bool}  $result
     */
    private function flashFromBounceResult(array $result): void
    {
        $this->flashType = 'success';
        if (($result['bounce_messages'] ?? 0) === 0 && ($result['emails_found'] ?? 0) === 0) {
            $this->flashMessage = __('platform.promo_campaigns.bounces_none', [
                'scanned' => $result['scanned'] ?? 0,
                'days' => ProcessPromoMailboxBouncesAction::DEFAULT_SINCE_DAYS,
            ]);

            return;
        }

        $this->flashMessage = __('platform.promo_campaigns.bounces_processed', [
            'scanned' => $result['scanned'] ?? 0,
            'bounces' => $result['bounce_messages'] ?? 0,
            'emails' => $result['emails_found'] ?? 0,
            'removed' => $result['removed'] ?? 0,
            'blocked' => $result['blocked'] ?? 0,
        ]);
    }

    private function consumeBounceScanCache(): void
    {
        $scan = ProcessPromoMailboxBouncesJob::status();
        if ($scan === null) {
            return;
        }

        $status = (string) ($scan['status'] ?? '');
        if (in_array($status, ['queued', 'running'], true)) {
            $this->bounceScanQueued = true;

            return;
        }

        if ($status === 'done' && isset($scan['result']) && is_array($scan['result'])) {
            $this->flashFromBounceResult($scan['result']);
            $this->bounceScanQueued = false;
            ProcessPromoMailboxBouncesJob::forgetStatus();

            return;
        }

        if ($status === 'failed') {
            $this->flashType = 'error';
            $message = trim((string) ($scan['error'] ?? ''));
            $this->flashMessage = $message !== ''
                ? __('platform.promo_campaigns.bounces_failed', ['error' => $message])
                : __('platform.promo_campaigns.bounces_failed_generic');
            $this->bounceScanQueued = false;
            ProcessPromoMailboxBouncesJob::forgetStatus();
        }
    }

    public function openPauseAllConfirm(): void
    {
        $this->authorize('managePromoCampaigns', User::class);
        $this->showPauseConfirm = true;
    }

    public function openDeleteConfirm(int $campaignId): void
    {
        $this->authorize('managePromoCampaigns', User::class);

        PromoCampaign::query()->findOrFail($campaignId);

        $this->showCopyModal = false;
        $this->deleteCampaignId = $campaignId;
        $this->showDeleteConfirm = true;
    }

    public function dismissDeleteConfirm(): void
    {
        $this->showDeleteConfirm = false;
        $this->deleteCampaignId = null;
    }

    public function deleteCampaign(DeletePromoCampaignAction $delete): void
    {
        $this->authorize('managePromoCampaigns', User::class);

        $user = auth()->user();
        if ($user === null || $this->deleteCampaignId === null) {
            return;
        }

        $campaign = PromoCampaign::query()->findOrFail($this->deleteCampaignId);
        $delete->handle($campaign, (int) $user->id);

        $this->dismissDeleteConfirm();
        $this->flashType = 'success';
        $this->flashMessage = __('platform.promo_campaigns.deleted_notice');
    }

    public function dismissPauseConfirm(): void
    {
        $this->showPauseConfirm = false;
    }

    public function confirmPauseAll(PausePromoCampaignSendingAction $pause): void
    {
        $this->authorize('managePromoCampaigns', User::class);

        $user = auth()->user();
        $result = $pause->handle(null, $user !== null ? (int) $user->id : null);

        $this->showPauseConfirm = false;
        $this->flashType = 'success';
        $this->flashMessage = __('platform.promo_campaigns.paused_notice', [
            'purged' => $result['purged_jobs'],
        ]);
    }

    public function resumeAllSending(ResumePromoCampaignSendingAction $resume): void
    {
        $this->authorize('managePromoCampaigns', User::class);

        $user = auth()->user();
        $resume->handle(null, $user !== null ? (int) $user->id : null);

        $this->flashType = 'success';
        $this->flashMessage = __('platform.promo_campaigns.resumed_notice');
    }

    public function render(SummarizePromoCampaignsDeliveryAction $summarize)
    {
        $this->consumeBounceScanCache();

        $campaigns = PromoCampaign::query()->latest('id')->get();

        return view('livewire.platform.promo-campaigns', [
            'campaigns' => $campaigns,
            'deliverySummaries' => $summarize->handle($campaigns),
            'anyPaused' => $campaigns->contains(fn (PromoCampaign $campaign): bool => $campaign->isEmailSendingPaused()),
            'bulkSendingEnabled' => (bool) config('winprox.promo_campaign_emails_enabled', true),
        ]);
    }
}
