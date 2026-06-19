<div class="wp-stack">
    <x-wp-page-head-title
        icon="document"
        :title="__('platform.promo_recipients.title')"
        :subtitle="__('platform.promo_recipients.subtitle')"
    />

    <section class="wp-card wp-card-pad wp-stack">
        <h2 class="wp-section-title">{{ __('platform.promo_recipients.anonymous_title') }}</h2>
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
    </section>

    <section class="wp-card wp-card-pad wp-stack">
        <h2 class="wp-section-title">{{ __('platform.promo_recipients.create_title') }}</h2>
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
    </section>

    <section class="wp-card wp-card-pad wp-stack">
        <h2 class="wp-section-title">{{ __('platform.promo_recipients.list_title') }}</h2>

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
    </section>
</div>
