<div class="wp-backoffice-install">
    <button
        type="button"
        class="btn btn--surface"
        data-wp-backoffice-install
        title="{{ __('common.pwa.install_tip') }}"
        wire:click="openHelp"
    >
        {{ __('common.pwa.install') }}
    </button>

    @if ($helpOpen)
        <x-wp-modal closeMethod="closeHelp" aria-labelledby="backoffice-pwa-title">
            <div class="wp-card wp-card-pad wp-stack wp-modal-card">
                <div class="wp-modal-head">
                    <h2 id="backoffice-pwa-title" class="wp-h2">{{ __('common.pwa.help_title') }}</h2>
                    <x-wp-modal-close wire:click="closeHelp" />
                </div>
                <p>{{ __('common.pwa.help_chrome') }}</p>
                <div class="wp-cluster">
                    <button type="button" class="btn btn--primary" wire:click="closeHelp">{{ __('common.button.close') }}</button>
                </div>
            </div>
        </x-wp-modal>
    @endif
</div>
