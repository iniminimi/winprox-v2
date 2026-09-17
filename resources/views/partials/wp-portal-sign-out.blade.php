{{-- Worker afmelden — één stijl op alle QR-portalen (.btn--surface). --}}
<button type="button" class="btn btn--surface btn--sm" onclick="window.wpFieldFlush?.()" wire:click="signInAsDifferentWorker">
    {{ __('portal.worker.sign_out') }}
</button>
