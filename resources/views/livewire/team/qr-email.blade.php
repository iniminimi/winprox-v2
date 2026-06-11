<div>
    <button type="button" class="btn btn--surface" wire:click="openModal">
        {{ __('team.qr.email') }}
    </button>

    @if ($showModal)
        <x-wp-modal closeMethod="closeModal" aria-labelledby="team-qr-email-title">
            <form wire:submit="send" class="wp-card wp-modal-card wp-modal-card--form">
                <div class="wp-modal-head wp-modal-head--bordered">
                    <h2 id="team-qr-email-title" class="wp-section-title">{{ __('team.qr.email_modal_title') }}</h2>
                    <x-wp-modal-close wire:click="closeModal" />
                </div>

                <div class="wp-modal-body wp-stack">
                    <p class="wp-muted wp-text-sm">{{ __('team.qr.email_modal_hint') }}</p>
                    <div class="wp-field">
                        <label class="wp-label" for="teamQrRecipientEmail">{{ __('team.qr.email_recipient_label') }}</label>
                        <input type="email" id="teamQrRecipientEmail" class="wp-input" wire:model="recipientEmail" autocomplete="email">
                        @error('recipientEmail') <p class="wp-error">{{ $message }}</p> @enderror
                    </div>
                    <div class="wp-field">
                        <label class="wp-label" for="teamQrRecipientName">{{ __('team.qr.email_recipient_name_label') }}</label>
                        <input type="text" id="teamQrRecipientName" class="wp-input" wire:model="recipientName" autocomplete="name">
                        @error('recipientName') <p class="wp-error">{{ $message }}</p> @enderror
                    </div>
                </div>

                <div class="wp-modal-foot">
                    <button type="button" class="btn btn--ghost" wire:click="closeModal">{{ __('common.button.cancel') }}</button>
                    <button type="submit" class="btn btn--primary" wire:loading.attr="disabled" wire:target="send">
                        <span wire:loading wire:target="send" class="wp-mr-2">
                            <x-wp-spinner size="sm" />
                        </span>
                        <span>{{ __('team.qr.email_send') }}</span>
                    </button>
                </div>
            </form>
        </x-wp-modal>
    @endif
</div>
