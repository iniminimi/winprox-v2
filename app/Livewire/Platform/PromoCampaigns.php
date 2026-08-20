<?php

namespace App\Livewire\Platform;

use App\Actions\Marketing\CopyPromoCampaignAction;
use App\Actions\Marketing\CreatePromoCampaignAction;
use App\Actions\Marketing\PausePromoCampaignSendingAction;
use App\Actions\Marketing\ProcessPromoMailboxBouncesAction;
use App\Actions\Marketing\ResumePromoCampaignSendingAction;
use App\Actions\Marketing\SummarizePromoCampaignsDeliveryAction;
use App\Http\Requests\Marketing\CopyPromoCampaignRequest;
use App\Http\Requests\Marketing\CreatePromoCampaignRequest;
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

    public ?string $flashMessage = null;

    public string $flashType = 'success';

    public bool $showCopyModal = false;

    public ?int $copyFromCampaignId = null;

    public string $copySlug = '';

    public string $copyName = '';

    public string $copyLocale = 'nl';

    public bool $showPauseConfirm = false;

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
        );

        $this->reset(['slug', 'name']);
        $this->locale = 'nl';

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

        $this->flashType = 'success';
        if ($result['bounce_messages'] === 0 && $result['emails_found'] === 0) {
            $this->flashMessage = __('platform.promo_campaigns.bounces_none', [
                'scanned' => $result['scanned'],
                'days' => ProcessPromoMailboxBouncesAction::DEFAULT_SINCE_DAYS,
            ]);

            return;
        }

        $this->flashMessage = __('platform.promo_campaigns.bounces_processed', [
            'scanned' => $result['scanned'],
            'bounces' => $result['bounce_messages'],
            'emails' => $result['emails_found'],
            'removed' => $result['removed'],
            'blocked' => $result['blocked'],
        ]);
    }

    public function openPauseAllConfirm(): void
    {
        $this->authorize('managePromoCampaigns', User::class);
        $this->showPauseConfirm = true;
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
        $campaigns = PromoCampaign::query()->latest('id')->get();

        return view('livewire.platform.promo-campaigns', [
            'campaigns' => $campaigns,
            'deliverySummaries' => $summarize->handle($campaigns),
            'anyPaused' => $campaigns->contains(fn (PromoCampaign $campaign): bool => $campaign->isEmailSendingPaused()),
            'bulkSendingEnabled' => (bool) config('winprox.promo_campaign_emails_enabled', true),
        ]);
    }
}
