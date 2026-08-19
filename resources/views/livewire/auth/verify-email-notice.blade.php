<div class="wp-stack">
    <div class="wp-stack">
        <h1 class="wp-section-title">{{ __('auth.verify.title') }}</h1>
        <p class="wp-muted">{{ __('auth.verify.subtitle', ['email' => $email]) }}</p>
    </div>

    @if ($status)
        <div class="wp-pill wp-pill--done">{{ $status }}</div>
    @endif

    <div class="wp-stack">
        <p class="wp-text-body">{{ __('auth.verify.body') }}</p>
        <p class="wp-muted wp-text-sm">{{ __('auth.verify.spam_hint') }}</p>

        <button type="button" class="btn btn--primary btn--block" wire:click="resend" wire:loading.attr="disabled">
            {{ __('auth.verify.resend') }}
        </button>

        <form method="POST" action="{{ route('logout') }}">
            @csrf
            <button type="submit" class="btn btn--ghost btn--block">{{ __('auth.verify.logout') }}</button>
        </form>

        <p class="wp-muted wp-text-sm">{{ __('auth.verify.wrong_email', ['email' => config('winprox.new_tenant_notification_email')]) }}</p>
    </div>
</div>
