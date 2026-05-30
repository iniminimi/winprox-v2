{{-- Sluitknop rechtsboven in wp-modal-head (icoon, geen tekst). wire:click via attributen doorgeven. --}}
<button
    type="button"
    class="btn btn--ghost btn--sm wp-modal-close"
    aria-label="{{ __('common.button.cancel') }}"
    {{ $attributes }}
>
    <x-wp-icon name="x-mark" class="wp-icon" />
</button>
