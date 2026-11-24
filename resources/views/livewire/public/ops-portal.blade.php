@push('head')
    <link rel="manifest" href="{{ route('public.ops.manifest') }}">
    <meta name="mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-title" content="OPS">
    <link rel="apple-touch-icon" href="{{ asset('images/pwa/winprox-192.png') }}">
    <meta name="theme-color" content="#059669">
    <script>
        (function () {
            var ua = navigator.userAgent || '';
            var ios = /iPad|iPhone|iPod/.test(ua) || (navigator.platform === 'MacIntel' && navigator.maxTouchPoints > 1);
            var android = /Android/i.test(ua);
            document.documentElement.classList.add(ios ? 'wp-homescreen-ios' : (android ? 'wp-homescreen-android' : 'wp-homescreen-other'));
        })();
    </script>
@endpush

<div class="wp-stack">
    <div class="wp-portal-head">
        <div class="wp-portal-head-top">
            <span class="wp-brand">
                <img src="{{ asset('images/Winprox_logo_100.png') }}" alt="WinProx">
            </span>
            <div class="wp-portal-head-tools">
                <div class="wp-cluster wp-cluster--tight">
                    @include('partials.wp-portal-theme')
                    @include('partials.wp-portal-lang')
                </div>
            </div>
        </div>
    </div>

    @if ($flashMessage)
        <div @class([
            'wp-flash',
            'wp-flash--success' => $flashType !== 'error',
            'wp-flash--danger' => $flashType === 'error',
        ])>{{ $flashMessage }}</div>
    @endif

    @if (! $authed || $screen === 'home')
        <div class="wp-card wp-card-pad wp-portal-homescreen-card wp-ops-homescreen-card wp-card--prio-pulse">
            @include('partials.wp-ops-homescreen-shortcut')
        </div>
    @endif

    @unless ($authed)
        <x-wp-page-head-title
            variant="portal"
            icon="qr"
            :title="__('ops.portal.title')"
            :subtitle="__('ops.portal.subtitle')"
        />

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
        @if ($screen === 'home')
            <x-wp-page-head-title
                variant="portal"
                icon="qr"
                :title="__('ops.portal.title')"
                :subtitle="__('ops.portal.subtitle')"
            />

            <div class="wp-cluster">
                <button type="button" class="btn btn--surface btn--sm" wire:click="logout">
                    {{ __('ops.portal.logout') }}
                </button>
            </div>

            <div class="wp-tiles">
                <button type="button" class="wp-tile" wire:click="openScreen('register')">
                    <span class="wp-cluster">
                        <x-wp-icon name="plus" class="wp-tile-icon" />
                        <span class="wp-tile-title">{{ __('ops.menu.register') }}</span>
                    </span>
                    <span class="wp-tile-sub">{{ __('ops.menu.register_hint') }}</span>
                </button>

                <button type="button" class="wp-tile" wire:click="openScreen('devices')">
                    <span class="wp-cluster">
                        <x-wp-icon name="search" class="wp-tile-icon" />
                        <span class="wp-tile-title">{{ __('ops.menu.devices') }}</span>
                    </span>
                    <span class="wp-tile-sub">{{ __('ops.menu.devices_hint') }}</span>
                </button>

                <button type="button" class="wp-tile" wire:click="openScreen('users')">
                    <span class="wp-cluster">
                        <x-wp-icon name="team" class="wp-tile-icon" />
                        <span class="wp-tile-title">{{ __('ops.menu.users') }}</span>
                    </span>
                    <span class="wp-tile-sub">{{ __('ops.menu.users_hint') }}</span>
                </button>

                <button type="button" class="wp-tile" wire:click="openScreen('help')">
                    <span class="wp-cluster">
                        <x-wp-icon name="help" class="wp-tile-icon" />
                        <span class="wp-tile-title">{{ __('ops.menu.help') }}</span>
                        @if ($unansweredCount > 0)
                            <span class="wp-pill wp-pill--new">{{ $unansweredCount }}</span>
                        @endif
                    </span>
                    <span class="wp-tile-sub">{{ __('ops.menu.help_hint') }}</span>
                </button>
            </div>
        @else
            <x-wp-portal-back wire:click="openScreen('home')" />

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
        @endif
    @endunless

    <div class="wp-pwa-open-screen" hidden role="dialog" aria-modal="true" aria-labelledby="wp-ops-pwa-open-title">
        <div class="wp-card wp-card-pad wp-stack wp-pwa-open-screen__card">
            <img src="{{ asset('images/pwa/winprox-192.png') }}" alt="" width="72" height="72">
            <h2 id="wp-ops-pwa-open-title" class="wp-h2">{{ __('ops.homescreen.open_title') }}</h2>
            <p class="wp-muted">{{ __('ops.homescreen.open_text') }}</p>
            <p class="wp-muted">{{ __('ops.homescreen.help_store') }}</p>
            <button type="button" class="btn btn--primary" data-wp-pwa-open-dismiss>{{ __('common.button.close') }}</button>
        </div>
    </div>

    @if ($homescreenHelpOpen)
        <x-wp-modal closeMethod="closeHomescreenHelp" aria-labelledby="ops-homescreen-help-title">
            <div class="wp-card wp-card-pad wp-stack wp-modal-card">
                <div class="wp-modal-head">
                    <h2 id="ops-homescreen-help-title" class="wp-h2">{{ __('ops.homescreen.help_title') }}</h2>
                    <x-wp-modal-close wire:click="closeHomescreenHelp" />
                </div>
                <p>{{ __('ops.homescreen.help_intro') }}</p>
                <div class="wp-homescreen-help__ios">
                    <p><strong>{{ __('ops.homescreen.help_ios_label') }}</strong></p>
                    <ol class="wp-homescreen-steps">
                        <li>{{ __('ops.homescreen.help_ios_1') }}</li>
                        <li>{{ __('ops.homescreen.help_ios_2') }}</li>
                        <li>{{ __('ops.homescreen.help_ios_3') }}</li>
                        <li>{{ __('ops.homescreen.help_ios_4') }}</li>
                    </ol>
                </div>
                <div class="wp-homescreen-help__android">
                    <p><strong>{{ __('ops.homescreen.help_android_label') }}</strong></p>
                    <p>{{ __('ops.homescreen.help_android') }}</p>
                </div>
                <p><strong>{{ __('ops.homescreen.help_done_title') }}</strong></p>
                <p>{{ __('ops.homescreen.help_done') }}</p>
                <p class="wp-muted">{{ __('ops.homescreen.help_store') }}</p>
                <div class="wp-cluster">
                    <button type="button" class="btn btn--primary" wire:click="closeHomescreenHelp">{{ __('common.button.close') }}</button>
                </div>
            </div>
        </x-wp-modal>
    @endif
</div>
