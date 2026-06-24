<div class="wp-stack">
    <x-wp-page-head-title
        icon="document"
        :title="__('platform.promo_recipients.title')"
        :subtitle="__('platform.promo_recipients.subtitle')"
    />

    <div class="wp-card wp-faq-item {{ $anonymousOpen ? 'is-open' : '' }}">
        <button
            type="button"
            class="wp-faq-trigger"
            wire:click="toggleSection('anonymous')"
            aria-expanded="{{ $anonymousOpen ? 'true' : 'false' }}"
        >
            <div class="wp-grow wp-stack-tight">
                <p class="wp-subhead">{{ __('platform.promo_recipients.anonymous_title') }}</p>
                @unless ($anonymousOpen)
                    <p class="wp-muted">{{ __('platform.promo_recipients.anonymous_count', ['count' => $anonymousVisitCount]) }}</p>
                @endunless
            </div>
            <span class="wp-faq-icon" aria-hidden="true">{{ $anonymousOpen ? '−' : '+' }}</span>
        </button>

        @if ($anonymousOpen)
            <div class="wp-faq-panel wp-card-pad wp-stack">
                <p class="wp-muted">{{ __('platform.promo_recipients.anonymous_lead') }}</p>
                <p class="wp-text-body">
                    {{ __('platform.promo_recipients.anonymous_count', ['count' => $anonymousVisitCount]) }}
                </p>
                <p class="wp-muted wp-text-sm">
                    {{ __('platform.promo_recipients.anonymous_url') }}
                    <code>{{ $anonymousPromoUrl }}</code>
                </p>
                <a href="{{ route('platform.promo-qr.download') }}" class="btn btn--ghost btn--sm">
                    {{ __('platform.promo_qr.download') }}
                </a>

                @if ($anonymousVisits->isNotEmpty())
                    <div class="wp-list wp-list--entity-rows wp-mt-4">
                        @foreach ($anonymousVisits as $visit)
                            <div class="wp-list-row" wire:key="anon-visit-{{ $visit->id }}">
                                <div class="wp-grow">
                                    <p class="wp-text-body">{{ $visit->visited_at?->format('d-m-Y H:i') }}</p>
                                    <p class="wp-muted wp-text-sm">{{ strtoupper($visit->locale) }}</p>
                                </div>
                            </div>
                        @endforeach
                    </div>
                @else
                    <p class="wp-muted">{{ __('platform.promo_recipients.anonymous_empty') }}</p>
                @endif
            </div>
        @endif
    </div>

    <div class="wp-card wp-faq-item {{ $statsOpen ? 'is-open' : '' }}">
        <button
            type="button"
            class="wp-faq-trigger"
            wire:click="toggleSection('stats')"
            aria-expanded="{{ $statsOpen ? 'true' : 'false' }}"
        >
            <div class="wp-grow wp-stack-tight">
                <p class="wp-subhead">{{ __('platform.promo_recipients.stats_title') }}</p>
                @unless ($statsOpen)
                    <p class="wp-muted">
                        @if ($recipientStats->isEmpty())
                            {{ __('platform.promo_recipients.stats_empty') }}
                        @else
                            {{ __('platform.promo_recipients.stats_summary', ['count' => $recipientStats->count()]) }}
                        @endif
                    </p>
                @endunless
            </div>
            <span class="wp-faq-icon" aria-hidden="true">{{ $statsOpen ? '−' : '+' }}</span>
        </button>

        @if ($statsOpen)
            <div class="wp-faq-panel wp-card-pad wp-stack">
                @if ($recipientStats->isEmpty())
                    <p class="wp-muted">{{ __('platform.promo_recipients.stats_empty') }}</p>
                @else
                    <div class="wp-list wp-list--entity-rows">
                        @foreach ($recipientStats as $recipient)
                            <div class="wp-list-row" wire:key="stats-{{ $recipient->id }}">
                                <p class="wp-text-body">
                                    {{ $recipient->label }},
                                    {{ __('platform.promo_recipients.visit_count', ['count' => $recipient->visits_count]) }},
                                    {{ __('platform.promo_recipients.video_count', ['count' => $recipient->video_plays_count]) }}
                                </p>
                            </div>
                        @endforeach
                    </div>
                @endif
            </div>
        @endif
    </div>

    <div class="wp-card wp-faq-item {{ $createOpen ? 'is-open' : '' }}">
        <button
            type="button"
            class="wp-faq-trigger"
            wire:click="toggleSection('create')"
            aria-expanded="{{ $createOpen ? 'true' : 'false' }}"
        >
            <div class="wp-grow wp-stack-tight">
                <p class="wp-subhead">{{ __('platform.promo_recipients.create_title') }}</p>
                @unless ($createOpen)
                    <p class="wp-muted">{{ __('platform.promo_recipients.create_lead') }}</p>
                @endunless
            </div>
            <span class="wp-faq-icon" aria-hidden="true">{{ $createOpen ? '−' : '+' }}</span>
        </button>

        @if ($createOpen)
            <div class="wp-faq-panel wp-card-pad wp-stack">
                <p class="wp-muted">{{ __('platform.promo_recipients.create_lead') }}</p>

                <form wire:submit="createRecipient" class="wp-stack-tight">
                    <div>
                        <label class="wp-label" for="promo-recipient-label">{{ __('platform.promo_recipients.label') }}</label>
                        <input
                            id="promo-recipient-label"
                            type="text"
                            class="wp-input"
                            wire:model="label"
                            autocomplete="off"
                            required
                        >
                        @error('label') <p class="wp-text-danger wp-text-sm">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label class="wp-label" for="promo-recipient-note">{{ __('platform.promo_recipients.note') }}</label>
                        <textarea
                            id="promo-recipient-note"
                            class="wp-input"
                            wire:model="note"
                            rows="2"
                        ></textarea>
                        @error('note') <p class="wp-text-danger wp-text-sm">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <button type="submit" class="btn btn--primary" wire:loading.attr="disabled">
                            <x-wp-spinner wire:loading wire:target="createRecipient" class="wp-mr-2" />
                            {{ __('platform.promo_recipients.create_submit') }}
                        </button>
                    </div>
                </form>
            </div>
        @endif
    </div>

    <div class="wp-card wp-faq-item {{ $listOpen ? 'is-open' : '' }}">
        <button
            type="button"
            class="wp-faq-trigger"
            wire:click="toggleSection('list')"
            aria-expanded="{{ $listOpen ? 'true' : 'false' }}"
        >
            <div class="wp-grow wp-stack-tight">
                <p class="wp-subhead">{{ __('platform.promo_recipients.list_title') }}</p>
                @unless ($listOpen)
                    <p class="wp-muted">
                        @if ($recipients->isEmpty())
                            {{ __('platform.promo_recipients.list_empty') }}
                        @else
                            {{ __('platform.promo_recipients.list_count', ['count' => $recipients->count()]) }}
                        @endif
                    </p>
                @endunless
            </div>
            <span class="wp-faq-icon" aria-hidden="true">{{ $listOpen ? '−' : '+' }}</span>
        </button>

        @if ($listOpen)
            <div class="wp-faq-panel wp-card-pad wp-stack">
                @if ($recipients->isEmpty())
                    <p class="wp-muted">{{ __('platform.promo_recipients.list_empty') }}</p>
                @else
                    <div class="wp-list wp-list--entity-rows">
                        @foreach ($recipients as $recipient)
                            <div class="wp-stack-tight" wire:key="recipient-{{ $recipient->id }}">
                                <div class="wp-list-row">
                                    <div class="wp-grow">
                                        <p class="wp-text-body"><strong>{{ $recipient->label }}</strong></p>
                                        @if ($recipient->note)
                                            <p class="wp-muted wp-text-sm">{{ $recipient->note }}</p>
                                        @endif
                                        <p class="wp-muted wp-text-sm">
                                            {{ __('platform.promo_recipients.visit_count', ['count' => $recipient->visits_count]) }}
                                            ·
                                            {{ __('platform.promo_recipients.video_count', ['count' => $recipient->video_plays_count]) }}
                                        </p>
                                        <p class="wp-muted wp-text-sm">
                                            @if ($recipient->latestSentEmailSend)
                                                {{ __('platform.promo_recipients.email_sent', [
                                                    'campaign' => $recipient->latestSentEmailSend->campaign,
                                                    'date' => $recipient->latestSentEmailSend->sent_at?->format('d-m-Y H:i'),
                                                ]) }}
                                            @elseif ($recipient->latestEmailSendAttempt?->status?->value === 'failed')
                                                {{ __('platform.promo_recipients.email_failed', [
                                                    'campaign' => $recipient->latestEmailSendAttempt->campaign,
                                                ]) }}
                                            @elseif ($recipient->latestEmailSendAttempt?->status?->value === 'pending')
                                                {{ __('platform.promo_recipients.email_pending', [
                                                    'campaign' => $recipient->latestEmailSendAttempt->campaign,
                                                ]) }}
                                            @else
                                                {{ __('platform.promo_recipients.email_not_sent') }}
                                            @endif
                                        </p>
                                        @if ($recipient->videoPlays->isNotEmpty())
                                            <p class="wp-muted wp-text-sm">
                                                {{ __('platform.promo_recipients.videos_played') }}:
                                                {{ $recipient->videoPlays->pluck('video_key')->join(', ') }}
                                            </p>
                                        @endif
                                    </div>
                                    <div class="wp-row wp-gap-sm">
                                        <button
                                            type="button"
                                            class="btn btn--ghost btn--sm"
                                            wire:click="toggleRecipient({{ $recipient->id }})"
                                        >
                                            {{ $expandedRecipientId === $recipient->id ? __('platform.promo_recipients.hide_visits') : __('platform.promo_recipients.show_visits') }}
                                        </button>
                                        <a href="{{ route('platform.promo-recipients.qr', $recipient) }}" class="btn btn--primary btn--sm">
                                            {{ __('platform.promo_recipients.download_qr') }}
                                        </a>
                                    </div>
                                </div>

                                @if ($expandedRecipientId === $recipient->id)
                                    @if ($expandedVisits->isEmpty())
                                        <p class="wp-muted wp-text-sm">{{ __('platform.promo_recipients.visits_empty') }}</p>
                                    @else
                                        <div class="wp-list wp-list--entity-rows">
                                            @foreach ($expandedVisits as $visit)
                                                <div class="wp-list-row" wire:key="visit-{{ $visit->id }}">
                                                    <div class="wp-grow">
                                                        <p class="wp-text-body">{{ $visit->visited_at?->format('d-m-Y H:i') }}</p>
                                                        <p class="wp-muted wp-text-sm">{{ strtoupper($visit->locale) }}</p>
                                                    </div>
                                                </div>
                                            @endforeach
                                        </div>
                                    @endif
                                @endif
                            </div>
                        @endforeach
                    </div>
                @endif
            </div>
        @endif
    </div>
</div>
