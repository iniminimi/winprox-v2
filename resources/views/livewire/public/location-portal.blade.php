<div class="wp-stack">
    <div class="wp-portal-head">
        <div class="wp-portal-head-top">
            <span class="wp-brand">WinProx</span>
            @include('partials.wp-portal-lang')
        </div>
        <p class="wp-muted">{{ $locationName }}</p>
    </div>

    @if ($inactiveReasonKey !== null)
        <div class="wp-card wp-card-pad wp-stack">
            <h1 class="wp-section-title">{{ __('portal.inactive.title') }}</h1>
            <p class="wp-muted">{{ __($inactiveReasonKey) }}</p>
        </div>
    @else
        @if ($flashMessage !== '')
            <div class="wp-flash">{{ $flashMessage }}</div>
        @endif

        @if ($portalSection === 'home')
            <div class="wp-tiles">
                <button type="button" class="wp-tile wp-tile--primary" wire:click="openSection('new')">
                    <span class="wp-tile-title">{{ __('portal.tiles.new') }}</span>
                    <span class="wp-tile-sub">{{ __('portal.tiles.new_sub') }}</span>
                </button>
            </div>

            @if ($units->isNotEmpty())
                <div class="wp-card wp-card-pad wp-stack-tight">
                    <h2 class="wp-section-title">{{ __('portal.location.units_title') }}</h2>
                    <p class="wp-hint">{{ __('portal.location.units_hint') }}</p>
                    <div class="wp-list">
                        @foreach ($units as $unit)
                            <a href="{{ route('public.unit-portal', $unit->qr_token) }}" class="wp-data-row" wire:key="unit-{{ $unit->id }}">
                                <span class="wp-data-row-title">{{ $unit->name }}</span>
                            </a>
                        @endforeach
                    </div>
                </div>
            @endif
        @endif

        @if ($portalSection === 'new')
            <button type="button" class="wp-back" wire:click="openSection('home')">&larr; {{ __('portal.back') }}</button>
            <h1 class="wp-page-title">{{ __('portal.report.title') }}</h1>
            <form x-data
                  @submit.prevent="await window.wpAwaitPhotoUploads($el); $wire.submitReport()"
                  class="wp-card wp-card-pad wp-stack">
                <div class="wp-field">
                    <label class="wp-label" for="description">{{ __('portal.report.description') }}</label>
                    <textarea id="description" class="wp-input" rows="4" wire:model="description"
                              placeholder="{{ __('portal.report.description_placeholder') }}"></textarea>
                    @error('description') <p class="wp-error">{{ $message }}</p> @enderror
                </div>
                <div class="wp-field">
                    <label class="wp-label">{{ __('portal.report.photos.label') }}</label>
                    @include('partials.wp-issue-photo-upload')
                    @error('photos') <p class="wp-error">{{ $message }}</p> @enderror
                    @error('photos.*') <p class="wp-error">{{ $message }}</p> @enderror
                </div>
                <button type="submit" class="btn btn--primary btn--block">
                    {{ __('portal.report.submit') }}
                </button>
            </form>
        @endif
    @endif
</div>
