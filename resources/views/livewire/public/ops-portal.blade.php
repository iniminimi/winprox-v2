<div class="wp-stack">
    <div class="wp-portal-head">
        <div class="wp-portal-head-top">
            <span class="wp-brand">
                <img src="{{ asset('images/Winprox_logo_100.png') }}" alt="WinProx" class="wp-ops-logo">
            </span>
            <div class="wp-cluster wp-cluster--tight">
                @include('partials.wp-portal-theme')
                @include('partials.wp-portal-lang')
            </div>
        </div>
        <p class="wp-section-title">{{ __('ops.portal.title') }}</p>
        <p class="wp-muted">{{ __('ops.portal.subtitle') }}</p>
    </div>

    @if ($flashMessage)
        <div @class([
            'wp-flash',
            'wp-flash--success' => $flashType !== 'error',
            'wp-flash--danger' => $flashType === 'error',
        ])>{{ $flashMessage }}</div>
    @endif

    @unless ($authed)
        <div class="wp-card wp-card-pad wp-stack">
            <p class="wp-subhead">{{ __('ops.portal.login_title') }}</p>
            <p class="wp-muted wp-text-sm">{{ __('ops.portal.login_hint') }}</p>
            <form wire:submit="login" class="wp-stack">
                <div>
                    <label class="wp-label" for="ops-login-email">{{ __('auth.email') }}</label>
                    <input id="ops-login-email" type="email" class="wp-input" wire:model="email" autocomplete="username">
                    @error('email') <p class="wp-error">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="wp-label" for="ops-login-password">{{ __('auth.password') }}</label>
                    <input id="ops-login-password" type="password" class="wp-input" wire:model="password" autocomplete="current-password">
                    @error('password') <p class="wp-error">{{ $message }}</p> @enderror
                </div>
                <button type="submit" class="btn btn--primary">{{ __('ops.portal.login_submit') }}</button>
            </form>
        </div>
    @else
        <div class="wp-cluster wp-cluster--wrap">
            @if ($screen !== 'home')
                <button type="button" class="btn btn--ghost btn--sm" wire:click="openScreen('home')">
                    {{ __('ops.menu.back') }}
                </button>
            @endif
            <button type="button" class="btn btn--ghost btn--sm" wire:click="logout">{{ __('ops.portal.logout') }}</button>
        </div>

        @if ($screen === 'home')
            <div class="wp-stack wp-ops-menu">
                <p class="wp-subhead">{{ __('ops.menu.title') }}</p>

                <button type="button" class="btn btn--surface wp-ops-menu__btn" wire:click="openScreen('register')">
                    <span class="wp-ops-menu__label">{{ __('ops.menu.register') }}</span>
                    <span class="wp-muted wp-text-sm">{{ __('ops.menu.register_hint') }}</span>
                </button>

                <button type="button" class="btn btn--surface wp-ops-menu__btn" wire:click="openScreen('devices')">
                    <span class="wp-ops-menu__label">{{ __('ops.menu.devices') }}</span>
                    <span class="wp-muted wp-text-sm">{{ __('ops.menu.devices_hint') }}</span>
                </button>

                <button type="button" class="btn btn--surface wp-ops-menu__btn" wire:click="openScreen('users')">
                    <span class="wp-ops-menu__label">{{ __('ops.menu.users') }}</span>
                    <span class="wp-muted wp-text-sm">{{ __('ops.menu.users_hint') }}</span>
                </button>

                <button type="button" class="btn btn--surface wp-ops-menu__btn" wire:click="openScreen('help')">
                    <span class="wp-ops-menu__label">
                        {{ __('ops.menu.help') }}
                        @if ($unansweredCount > 0)
                            <span class="wp-pill wp-pill--progress">{{ __('ops.menu.help_open', ['count' => $unansweredCount]) }}</span>
                        @endif
                    </span>
                    <span class="wp-muted wp-text-sm">{{ __('ops.menu.help_hint') }}</span>
                </button>
            </div>
        @endif

        @if ($screen === 'register')
            @include('livewire.public.ops.partials.register')
        @endif

        @if ($screen === 'devices')
            @include('livewire.public.ops.partials.devices')
        @endif

        @if ($screen === 'users')
            @include('livewire.public.ops.partials.users')
        @endif

        @if ($screen === 'help')
            @include('livewire.public.ops.partials.help')
        @endif
    @endunless
</div>
