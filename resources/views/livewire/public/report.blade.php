<div class="wp-stack">
    <div class="wp-portal-head">
        <span class="wp-brand">WinProx</span>
        <h1 class="wp-page-title">{{ __('report.title') }}</h1>
        <p class="wp-muted">
            @if ($locationName){{ $locationName }} &middot; @endif{{ $unitName }}
        </p>
    </div>

    @if ($submitted)
        <div class="wp-card wp-card-pad wp-stack wp-portal-confirm">
            <h2 class="wp-section-title">{{ __('report.confirm.title') }}</h2>
            <p class="wp-text-body">{{ __('report.confirm.body') }}</p>
        </div>
    @else
        <form x-data
              @submit.prevent="await window.wpAwaitPhotoUploads($el); $wire.submit()"
              class="wp-stack">
            <div class="wp-card wp-card-pad wp-stack">
                <div class="wp-field">
                    <label class="wp-label" for="description">{{ __('report.fields.description') }}</label>
                    <textarea id="description"
                              class="wp-textarea"
                              wire:model="description"
                              rows="5"
                              placeholder="{{ __('report.fields.description_placeholder') }}"></textarea>
                    @error('description') <p class="wp-error">{{ $message }}</p> @enderror
                </div>

                <div class="wp-field">
                    <label class="wp-label">{{ __('report.fields.photos') }}</label>
                    @include('partials.wp-issue-photo-upload')
                    @error('photos.*') <p class="wp-error">{{ $message }}</p> @enderror
                    @error('photos') <p class="wp-error">{{ $message }}</p> @enderror
                </div>

                <div class="wp-field">
                    <label class="wp-label" for="reporter_name">{{ __('report.fields.reporter_name') }}</label>
                    <input id="reporter_name"
                           type="text"
                           class="wp-input"
                           wire:model="reporter_name"
                           placeholder="{{ __('report.fields.optional') }}">
                    @error('reporter_name') <p class="wp-error">{{ $message }}</p> @enderror
                </div>

                <div class="wp-field">
                    <label class="wp-label" for="reporter_contact">{{ __('report.fields.reporter_contact') }}</label>
                    <input id="reporter_contact"
                           type="text"
                           class="wp-input"
                           wire:model="reporter_contact"
                           placeholder="{{ __('report.fields.optional') }}">
                    @error('reporter_contact') <p class="wp-error">{{ $message }}</p> @enderror
                </div>
            </div>

            <div class="wp-portal-actions">
                <button type="submit" class="btn btn--primary btn--block" wire:loading.attr="disabled">
                    {{ __('report.submit') }}
                </button>
            </div>
        </form>
    @endif
</div>
