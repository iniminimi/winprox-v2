<div class="wp-card wp-card-pad wp-stack">
    <div class="wp-stack">
        <h1 class="wp-page-title">{{ __('auth.title') }}</h1>
        <p class="wp-muted">{{ __('auth.subtitle') }}</p>
    </div>

    <form wire:submit="login" class="wp-stack">
        <div class="wp-field">
            <label class="wp-label" for="email">{{ __('auth.email') }}</label>
            <input type="email" id="email" class="wp-input" wire:model="email" autocomplete="email" autofocus>
            @error('email') <p class="wp-error">{{ $message }}</p> @enderror
        </div>

        <div class="wp-field">
            <label class="wp-label" for="password">{{ __('auth.password') }}</label>
            <input type="password" id="password" class="wp-input" wire:model="password" autocomplete="current-password">
            @error('password') <p class="wp-error">{{ $message }}</p> @enderror
        </div>

        <label class="wp-check">
            <input type="checkbox" wire:model="remember">
            {{ __('auth.remember') }}
        </label>

        <button type="submit" class="btn btn--primary btn--block">{{ __('auth.submit') }}</button>
    </form>
</div>
