<div class="wp-stack">
    <x-wp-page-head-title
        icon="contact"
        :title="__('platform.email_unsubscribe.title')"
        help-page="platform.email_unsubscribes"
        :subtitle="__('platform.email_unsubscribe.subtitle')"
    />

    @if ($flashMessage)
        <div @class([
            'wp-flash',
            'wp-flash--success' => $flashType !== 'error',
            'wp-flash--danger' => $flashType === 'error',
        ])>{{ $flashMessage }}</div>
    @endif

    <div class="wp-card wp-card-pad wp-stack">
        <p class="wp-subhead">{{ __('platform.email_unsubscribe.add_title') }}</p>
        <form wire:submit="add" class="wp-stack">
            <div>
                <label class="wp-label" for="unsubscribe-new-email">{{ __('platform.email_unsubscribe.email') }}</label>
                <input id="unsubscribe-new-email" type="email" class="wp-input" wire:model="newEmail" autocomplete="off">
                @error('newEmail') <p class="wp-error">{{ $message }}</p> @enderror
            </div>
            <div>
                <button type="submit" class="btn btn--primary">{{ __('platform.email_unsubscribe.add_submit') }}</button>
            </div>
        </form>
    </div>

    <div class="wp-card wp-card-pad wp-stack">
        <div class="wp-row wp-row--wrap">
            <label class="wp-label" for="unsubscribe-search">{{ __('platform.email_unsubscribe.search') }}</label>
            <label class="wp-check" for="unsubscribe-undeliverable-only">
                <input
                    id="unsubscribe-undeliverable-only"
                    type="checkbox"
                    wire:model.live="undeliverableOnly"
                >
                <span>{{ __('platform.email_unsubscribe.filter_undeliverable', ['count' => $undeliverableCount]) }}</span>
            </label>
            <label class="wp-check" for="unsubscribe-manual-only">
                <input
                    id="unsubscribe-manual-only"
                    type="checkbox"
                    wire:model.live="manualOnly"
                >
                <span>{{ __('platform.email_unsubscribe.filter_manual', ['count' => $manualCount]) }}</span>
            </label>
        </div>
        <input id="unsubscribe-search" type="search" class="wp-input" wire:model.live.debounce.300ms="search"
               placeholder="{{ __('platform.email_unsubscribe.search_placeholder') }}" autocomplete="off">

        @if ($rows->isEmpty())
            <p class="wp-muted">{{ __('platform.email_unsubscribe.empty') }}</p>
        @else
            <div class="wp-list wp-list--entity-rows">
                @foreach ($rows as $row)
                    @php
                        $matchedUser = $matchedUsers[\App\Models\EmailUnsubscribe::normalizeEmail($row->email)] ?? null;
                    @endphp
                    <div class="wp-list-row" wire:key="unsubscribe-{{ $row->id }}">
                        <div class="wp-grow">
                            <p class="wp-text-body">
                                <strong>{{ $row->email }}</strong><span class="wp-muted wp-text-sm">, {{ mb_strtolower(__($row->source->labelKey()), 'UTF-8') }} {{ __('platform.email_unsubscribe.list_date_prefix') }} {{ $row->unsubscribed_at->format('d-m-Y H:i') }}</span>
                                @if ($matchedUser)
                                    <span class="wp-muted wp-text-sm">
                                        · {{ $matchedUser->name }}
                                        @if ($matchedUser->tenant)
                                            ({{ $matchedUser->tenant->name }})
                                        @endif
                                    </span>
                                @endif
                            </p>
                        </div>
                        <button
                            type="button"
                            class="btn btn--ghost btn--sm"
                            wire:click="restore({{ $row->id }})"
                            wire:confirm="{{ __('platform.email_unsubscribe.confirm_restore') }}"
                        >
                            {{ __('platform.email_unsubscribe.restore') }}
                        </button>
                    </div>
                @endforeach
            </div>

            <div>
                {{ $rows->links() }}
            </div>
        @endif
    </div>
</div>
