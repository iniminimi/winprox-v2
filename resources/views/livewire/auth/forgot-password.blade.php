<div class="wp-stack">
    <div class="wp-stack">
        <h1 class="wp-section-title">{{ __('auth.forgot.title') }}</h1>
        <p class="wp-muted">{{ __('auth.forgot.subtitle') }}</p>
    </div>

    @if ($status)
        <div class="wp-pill wp-pill--done">{{ $status }}</div>
    @endif

    <form wire:submit="sendResetLink" class="wp-stack">
        <div class="wp-field">
            <label class="wp-label" for="email">{{ __('auth.email') }}</label>
            <input type="email" id="email" class="wp-input" wire:model="email" autocomplete="email" autofocus>
            @error('email') <p class="wp-error">{{ $message }}</p> @enderror
        </div>

        <button type="submit" class="btn btn--primary btn--block">{{ __('auth.forgot.submit') }}</button>
        <a href="{{ route('login') }}" class="btn btn--ghost btn--block">{{ __('auth.forgot.back_to_login') }}</a>
    </form>
</div>
