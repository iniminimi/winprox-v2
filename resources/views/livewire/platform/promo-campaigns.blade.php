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
                <button type="submit" class="btn btn--primary">{{ __('platform.promo_campaigns.create_submit') }}</button>
            </div>
        </form>
    </div>

    <div class="wp-card wp-card-pad wp-stack">
        <p class="wp-subhead">{{ __('platform.promo_campaigns.list_title') }}</p>
        @if ($campaigns->isEmpty())
            <p class="wp-muted">{{ __('platform.promo_campaigns.list_empty') }}</p>
        @else
            <div class="wp-list wp-list--entity-rows">
                @foreach ($campaigns as $campaign)
                    <div class="wp-list-row" wire:key="campaign-{{ $campaign->id }}">
                        <div class="wp-grow">
                            <p class="wp-text-body"><strong>{{ $campaign->name }}</strong></p>
                            <p class="wp-muted wp-text-sm">{{ $campaign->slug }} · {{ strtoupper($campaign->locale) }}</p>
                        </div>
                        <a href="{{ route('platform.promo-campaigns.edit', $campaign) }}" class="btn btn--ghost btn--sm" wire:navigate>
                            {{ __('platform.promo_campaigns.open') }}
                        </a>
                    </div>
                @endforeach
            </div>
        @endif
    </div>
</div>
