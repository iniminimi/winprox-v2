<div class="wp-stack">
    <div class="wp-stack">
        <h1 class="wp-section-title">{{ __('auth.verify.title') }}</h1>
        <p class="wp-muted">{{ __('auth.verify.subtitle', ['email' => $email]) }}</p>
    </div>

    @if ($status)
        <div class="wp-pill wp-pill--done">{{ $status }}</div>
    @endif
    @error('email') <p class="wp-error">{{ $message }}</p> @enderror

    <div class="wp-stack">
        <p class="wp-text-body">{{ __('auth.verify.body') }}</p>
        <p class="wp-muted wp-text-sm">{{ __('auth.verify.spam_hint') }}</p>

        <button type="button" class="btn btn--primary btn--block" wire:click="resend" wire:loading.attr="disabled" wire:target="resend">
            <x-wp-spinner wire:loading wire:target="resend" class="wp-mr-2" />
            <span wire:loading.remove wire:target="resend">{{ __('auth.verify.resend') }}</span>
            <span wire:loading wire:target="resend">{{ __('auth.verify.resend_loading') }}</span>
        </button>
        <p class="wp-muted wp-text-sm" wire:loading.delay.longest wire:target="resend">{{ __('auth.verify.checking_email') }}</p>

        <form method="POST" action="{{ route('logout') }}">
            @csrf
            <button type="submit" class="btn btn--ghost btn--block">{{ __('auth.verify.logout') }}</button>
        </form>

        <p class="wp-muted wp-text-sm">{{ __('auth.verify.wrong_email', ['email' => config('winprox.new_tenant_notification_email')]) }}</p>
    </div>
</div>
