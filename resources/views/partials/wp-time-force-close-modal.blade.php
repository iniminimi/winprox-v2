@if ($showForceCloseModal)
    <x-wp-modal closeMethod="closeForceClose" aria-labelledby="force-close-title">
        <form wire:submit="confirmForceClose" class="wp-card wp-card-pad wp-stack wp-modal-card">
            <div class="wp-modal-head">
                <h2 id="force-close-title" class="wp-h2">{{ __('time.force_close.title') }}</h2>
                <x-wp-modal-close wire:click="closeForceClose" />
            </div>
            <p class="wp-muted wp-text-sm">{{ __('time.force_close.subtitle', ['worker' => $forceCloseWorkerLabel]) }}</p>
            <div class="wp-field">
                <label class="wp-label" for="force-close-reason">{{ __('time.force_close.fields.reason') }}</label>
                <textarea id="force-close-reason" class="wp-input" rows="3" wire:model="forceCloseReason" required></textarea>
                @error('forceCloseReason') <p class="wp-field-error">{{ $message }}</p> @enderror
            </div>
            <div class="wp-cluster">
                <button type="button" class="btn btn--surface" wire:click="closeForceClose">{{ __('common.button.cancel') }}</button>
                <button type="submit" class="btn btn--primary">{{ __('time.force_close.submit') }}</button>
            </div>
        </form>
    </x-wp-modal>
@endif
