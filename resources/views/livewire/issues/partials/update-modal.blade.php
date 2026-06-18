<x-wp-modal closeMethod="closeUpdateModal" aria-labelledby="issue-update-title">
    <form
        x-data
        x-init="queueMicrotask(() => window.wpRefreshAllPhotoUploadAreas?.())"
        x-on:submit.prevent="(async () => { await window.wpAwaitPhotoUploads?.($el); await $wire.saveUpdate(); })()"
        class="wp-card wp-modal-card wp-modal-card--form"
    >
        <div class="wp-modal-head wp-modal-head--bordered">
            <h2 id="issue-update-title" class="wp-section-title">{{ __('issues.show.add_update_modal_title') }}</h2>
            <x-wp-modal-close wire:click="closeUpdateModal" />
        </div>

        <div class="wp-modal-body wp-stack">
            <div class="wp-card wp-card-pad wp-surface-muted wp-stack-tight">
                <p class="wp-label">{{ __('issues.show.add_update_modal_original') }}</p>
                @if ($issue->localizedDescription())
                    <p class="wp-muted">{{ $issue->localizedDescription() }}</p>
                @endif
            </div>

            <div class="wp-field">
                <div x-data="{ n: 0, max: {{ \App\Support\Validation\TextDescriptionLimits::MAX }} }">
                    <textarea
                        id="updateDescription"
                        class="wp-textarea"
                        wire:model="updateDescription"
                        rows="4"
                        placeholder="{{ __('issues.show.add_update_placeholder') }}"
                        required
                        maxlength="{{ \App\Support\Validation\TextDescriptionLimits::MAX }}"
                        x-init="n = $el.value.length" x-on:input="n = $el.value.length"
                    ></textarea>
                    <p class="wp-char-counter" :class="{ 'wp-char-counter--near': n >= max - 50, 'wp-char-counter--full': n >= max }"><span x-text="n"></span>/<span x-text="max"></span></p>
                </div>
                @error('updateDescription') <p class="wp-error">{{ $message }}</p> @enderror
            </div>

            <div class="wp-field">
                <label class="wp-label">{{ __('issues.show.add_update_photos') }}</label>
                @include('partials.wp-issue-photo-upload', [
                    'model' => 'updatePhotos',
                    'removeMethod' => 'removeUpdatePhoto',
                ])
                @error('updatePhotos') <p class="wp-error">{{ $message }}</p> @enderror
                @error('updatePhotos.*') <p class="wp-error">{{ $message }}</p> @enderror
            </div>
        </div>

        <div class="wp-modal-foot">
            <button type="button" class="btn btn--ghost" wire:click="closeUpdateModal">{{ __('common.button.cancel') }}</button>
            <button type="submit" class="btn btn--primary" wire:loading.attr="disabled">
                <x-wp-spinner wire:loading class="wp-mr-2" />
                <span wire:loading.remove>{{ __('issues.show.add_update_submit') }}</span>
                <span wire:loading>{{ __('issues.show.add_update_submit_loading') }}</span>
            </button>
        </div>
    </form>
</x-wp-modal>
