<div class="wp-stack">
    <x-wp-page-head-title
        icon="document"
        :title="__('platform.promo_campaigns.title')"
        :subtitle="__('platform.promo_campaigns.subtitle')"
    />

    @if ($flashMessage)
        <div @class([
            'wp-flash',
            'wp-flash--success' => $flashType !== 'error',
            'wp-flash--danger' => $flashType === 'error',
        ])>{{ $flashMessage }}</div>
    @endif

    @if (! $bulkSendingEnabled)
        <div class="wp-flash wp-flash--danger">{{ __('platform.promo_campaigns.queue_disabled') }}</div>
    @endif

    <div class="wp-card wp-card-pad wp-stack">
        <p class="wp-subhead">{{ __('platform.promo_campaigns.pause_all_title') }}</p>
        <p class="wp-muted">{{ __('platform.promo_campaigns.pause_all_lead') }}</p>
        <div class="wp-row wp-row--gap-sm wp-wrap">
            <button type="button" class="btn btn--danger" wire:click="openPauseAllConfirm">
                {{ __('platform.promo_campaigns.pause_all_submit') }}
            </button>
            @if ($anyPaused)
                <button type="button" class="btn btn--ghost" wire:click="resumeAllSending">
                    {{ __('platform.promo_campaigns.resume_all_submit') }}
                </button>
            @endif
        </div>
    </div>

    <div class="wp-card wp-card-pad wp-stack">
        <p class="wp-subhead">{{ __('platform.promo_campaigns.create_title') }}</p>
        <form wire:submit="createCampaign" class="wp-promo-form-grid">
            <div>
                <label class="wp-label" for="campaign-slug">{{ __('platform.promo_campaigns.slug') }}</label>
                <input id="campaign-slug" type="text" class="wp-input" wire:model.live="slug" placeholder="wallonie-wave-1">
                <p class="wp-muted wp-text-sm">{{ __('platform.promo_campaigns.slug_hint') }}</p>
                @error('slug') <p class="wp-error">{{ $message }}</p> @enderror
            </div>
            <div class="wp-promo-form-grid__pair">
                <div>
                    <label class="wp-label" for="campaign-name">{{ __('platform.promo_campaigns.name') }}</label>
                    <input id="campaign-name" type="text" class="wp-input" wire:model="name">
                    @error('name') <p class="wp-error">{{ $message }}</p> @enderror
                </div>
                    <div class="wp-promo-form-grid__locale">
                    <label class="wp-label" for="campaign-locale">{{ __('platform.promo_campaigns.locale') }}</label>
                    <select id="campaign-locale" class="wp-select" wire:model="locale">
                        @foreach (config('locales.supported', []) as $localeCode)
                            <option value="{{ $localeCode }}">{{ config('locales.labels.'.$localeCode, strtoupper($localeCode)) }}</option>
                        @endforeach
                    </select>
                    @error('locale') <p class="wp-error">{{ $message }}</p> @enderror
                </div>
            </div>
            <div>
                <label class="wp-label" for="campaign-landing">{{ __('platform.promo_campaigns.landing') }}</label>
                <select id="campaign-landing" class="wp-select" wire:model="landing">
                    @foreach (\App\Enums\PromoLanding::cases() as $landingOption)
                        <option value="{{ $landingOption->value }}">{{ __($landingOption->labelKey()) }}</option>
                    @endforeach
                </select>
                <p class="wp-muted wp-text-sm">{{ __('platform.promo_campaigns.landing_hint') }}</p>
                @error('landing') <p class="wp-error">{{ $message }}</p> @enderror
            </div>
            <div>
                <button type="submit" class="btn btn--primary">{{ __('platform.promo_campaigns.create_submit') }}</button>
            </div>
        </form>
    </div>

    <div class="wp-card wp-card-pad wp-stack">
        <p class="wp-subhead">{{ __('platform.promo_campaigns.bounces_title') }}</p>
        <p class="wp-muted">{{ __('platform.promo_campaigns.bounces_lead') }}</p>
        <div>
            <button
                type="button"
                class="btn btn--ghost"
                wire:click="processPromoBounces"
                wire:loading.attr="disabled"
                wire:target="processPromoBounces"
                @disabled($bounceScanQueued)
            >
                <span wire:loading.remove wire:target="processPromoBounces">
                    {{ $bounceScanQueued
                        ? __('platform.promo_campaigns.bounces_loading')
                        : __('platform.promo_campaigns.bounces_submit') }}
                </span>
                <span wire:loading wire:target="processPromoBounces">
                    {{ __('platform.promo_campaigns.bounces_loading') }}
                </span>
            </button>
        </div>
    </div>

    <div class="wp-card wp-card-pad wp-stack" @if ($bounceScanQueued) wire:poll.visible.5s @else wire:poll.visible.30s @endif>
        <p class="wp-subhead">{{ __('platform.promo_campaigns.list_title') }}</p>
        @if ($campaigns->isEmpty())
            <p class="wp-muted">{{ __('platform.promo_campaigns.list_empty') }}</p>
        @else
            <div class="wp-list wp-list--entity-rows">
                @foreach ($campaigns as $campaign)
                    @php
                        $summary = $deliverySummaries[$campaign->id] ?? null;
                    @endphp
                    <div class="wp-list-row" wire:key="campaign-{{ $campaign->id }}">
                        <div class="wp-grow">
                            <p class="wp-text-body"><strong>{{ $campaign->name }}</strong></p>
                            @php
                                $timezone = config('app.timezone');
                                $createdDate = $campaign->created_at?->timezone($timezone)->format('d-m-Y');
                                $firstSent = $summary?->firstSentAt?->timezone($timezone);
                                $lastSent = $summary?->lastSentAt?->timezone($timezone);
                            @endphp
                            <p class="wp-muted wp-text-sm">
                                {{ $campaign->slug }} · {{ strtoupper($campaign->locale) }}
                                @if ($createdDate)
                                    · {{ __('platform.promo_campaigns.list_created', ['date' => $createdDate]) }}
                                @endif
                                @if ($firstSent && $lastSent)
                                    ·
                                    @if ($firstSent->toDateString() === $lastSent->toDateString())
                                        {{ __('platform.promo_campaigns.list_ran', ['date' => $firstSent->format('d-m-Y')]) }}
                                    @else
                                        {{ __('platform.promo_campaigns.list_ran_range', [
                                            'from' => $firstSent->format('d-m-Y'),
                                            'to' => $lastSent->format('d-m-Y'),
                                        ]) }}
                                    @endif
                                @endif
                            </p>
                            @if ($summary)
                                <div class="wp-stack-tight">
                                    <span class="{{ $summary->pillClass() }}">
                                        {{ __($summary->status->labelKey()) }}
                                    </span>
                                    <p class="wp-muted wp-text-sm">
                                        {{ __('platform.promo_campaigns.delivery_stats', [
                                            'sent' => $summary->sent,
                                            'remaining' => $summary->remaining,
                                            'failed' => $summary->failed,
                                            'bounced' => $summary->bounced,
                                            'bounce_percent' => $summary->bouncePercent,
                                            'queued' => $summary->queuedJobs,
                                        ]) }}
                                    </p>
                                    @if ($summary->bounced > 0)
                                        <p class="wp-muted wp-text-sm">
                                            {{ __('platform.promo_campaigns.delivery_bounce_kinds', [
                                                'unknown' => $summary->bounceUnknown,
                                                'blacklist' => $summary->bounceBlacklist,
                                                'mailbox_full' => $summary->bounceMailboxFull,
                                                'spam' => $summary->bounceSpam,
                                                'domain_block' => $summary->bounceDomainBlock,
                                            ]) }}
                                        </p>
                                    @endif
                                    @if ($summary->status === \App\Enums\PromoCampaignDeliveryStatus::NeedsRestart)
                                        <p class="wp-text-sm">{{ __('platform.promo_campaigns.delivery_restart_hint') }}</p>
                                    @elseif ($summary->status === \App\Enums\PromoCampaignDeliveryStatus::Sending)
                                        <p class="wp-text-sm">{{ __('platform.promo_campaigns.delivery_sending_hint') }}</p>
                                    @elseif ($summary->status === \App\Enums\PromoCampaignDeliveryStatus::Paused)
                                        <p class="wp-text-sm">{{ __('platform.promo_campaigns.delivery_paused_hint') }}</p>
                                        @if ($campaign->emailsPauseReasonLabelKey())
                                            <p class="wp-muted wp-text-sm">
                                                {{ __('platform.promo_campaigns.paused_reason_label') }}
                                                {{ __($campaign->emailsPauseReasonLabelKey()) }}
                                            </p>
                                        @endif
                                        @if (filled($campaign->emails_paused_detail))
                                            <p class="wp-muted wp-text-sm">{{ $campaign->emails_paused_detail }}</p>
                                        @endif
                                    @endif
                                </div>
                            @endif
                        </div>
                        <div class="wp-row wp-row--gap-sm wp-wrap">
                            <button type="button" class="btn btn--ghost btn--sm" wire:click="openCopyModal({{ $campaign->id }})">
                                {{ __('platform.promo_campaigns.copy') }}
                            </button>
                            <a href="{{ route('platform.promo-campaigns.edit', $campaign) }}" class="btn btn--ghost btn--sm" wire:navigate>
                                {{ __('platform.promo_campaigns.open') }}
                            </a>
                            <button type="button" class="btn btn--danger btn--sm" wire:click="openDeleteConfirm({{ $campaign->id }})">
                                {{ __('platform.promo_campaigns.delete_submit') }}
                            </button>
                        </div>
                    </div>
                @endforeach
            </div>
        @endif
    </div>

    @if ($showCopyModal)
        <x-wp-modal closeMethod="closeCopyModal" aria-labelledby="promo-copy-title">
            <form wire:submit="copyCampaign" class="wp-card wp-modal-card wp-modal-card--form">
                <div class="wp-modal-head wp-modal-head--bordered">
                    <div class="wp-stack-tight">
                        <h2 id="promo-copy-title" class="wp-section-title">{{ __('platform.promo_campaigns.copy_title') }}</h2>
                        <p class="wp-muted wp-text-sm">{{ __('platform.promo_campaigns.copy_lead') }}</p>
                    </div>
                    <x-wp-modal-close wire:click="closeCopyModal" />
                </div>

                <div class="wp-modal-body wp-stack">
                    <div>
                        <label class="wp-label" for="copy-slug">{{ __('platform.promo_campaigns.slug') }}</label>
                        <input id="copy-slug" type="text" class="wp-input" wire:model.live="copySlug" placeholder="wallonie-wave-2">
                        <p class="wp-muted wp-text-sm">{{ __('platform.promo_campaigns.slug_hint') }}</p>
                        @error('copySlug') <p class="wp-error">{{ $message }}</p> @enderror
                    </div>
                    <div class="wp-promo-form-grid__pair">
                        <div>
                            <label class="wp-label" for="copy-name">{{ __('platform.promo_campaigns.name') }}</label>
                            <input id="copy-name" type="text" class="wp-input" wire:model="copyName">
                            @error('copyName') <p class="wp-error">{{ $message }}</p> @enderror
                        </div>
                        <div class="wp-promo-form-grid__locale">
                            <label class="wp-label" for="copy-locale">{{ __('platform.promo_campaigns.locale') }}</label>
                            <select id="copy-locale" class="wp-select" wire:model="copyLocale">
                                @foreach (config('locales.supported', []) as $localeCode)
                                    <option value="{{ $localeCode }}">{{ config('locales.labels.'.$localeCode, strtoupper($localeCode)) }}</option>
                                @endforeach
                            </select>
                            @error('copyLocale') <p class="wp-error">{{ $message }}</p> @enderror
                        </div>
                    </div>
                </div>

                <div class="wp-modal-foot wp-modal-foot--bordered wp-row wp-row--gap-sm">
                    <button type="button" class="btn btn--ghost" wire:click="closeCopyModal">{{ __('common.button.cancel') }}</button>
                    <button type="submit" class="btn btn--primary">{{ __('platform.promo_campaigns.copy_submit') }}</button>
                </div>
            </form>
        </x-wp-modal>
    @endif

    @if ($showDeleteConfirm)
        <x-wp-modal closeMethod="dismissDeleteConfirm">
            <div class="wp-card wp-card-pad wp-stack wp-modal-card" role="alertdialog" aria-labelledby="promo-campaign-delete-title">
                <div class="wp-modal-head">
                    <h2 id="promo-campaign-delete-title" class="wp-section-title">{{ __('platform.promo_campaigns.delete_confirm_title') }}</h2>
                    <x-wp-modal-close wire:click="dismissDeleteConfirm" />
                </div>
                <div class="wp-modal-body">
                    <p class="wp-text-body">{{ __('platform.promo_campaigns.delete_confirm_body') }}</p>
                </div>
                <div class="wp-modal-foot wp-row wp-row--gap-sm">
                    <button type="button" class="btn btn--ghost" wire:click="dismissDeleteConfirm">{{ __('common.button.cancel') }}</button>
                    <button type="button" class="btn btn--danger" wire:click="deleteCampaign">{{ __('platform.promo_campaigns.delete_submit') }}</button>
                </div>
            </div>
        </x-wp-modal>
    @endif

    @if ($showPauseConfirm)
        <x-wp-modal closeMethod="dismissPauseConfirm">
            <div class="wp-card wp-card-pad wp-stack wp-modal-card" role="alertdialog" aria-labelledby="promo-pause-all-title">
                <div class="wp-modal-head">
                    <h2 id="promo-pause-all-title" class="wp-section-title">{{ __('platform.promo_campaigns.pause_all_confirm_title') }}</h2>
                    <x-wp-modal-close wire:click="dismissPauseConfirm" />
                </div>
                <div class="wp-modal-body">
                    <p class="wp-text-body">{{ __('platform.promo_campaigns.pause_all_confirm_body') }}</p>
                </div>
                <div class="wp-modal-foot wp-row wp-row--gap-sm">
                    <button type="button" class="btn btn--ghost" wire:click="dismissPauseConfirm">{{ __('common.button.cancel') }}</button>
                    <button type="button" class="btn btn--danger" wire:click="confirmPauseAll">{{ __('platform.promo_campaigns.pause_all_confirm_submit') }}</button>
                </div>
            </div>
        </x-wp-modal>
    @endif
</div>
