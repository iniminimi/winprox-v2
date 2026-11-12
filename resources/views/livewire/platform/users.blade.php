<div class="wp-stack">
    <x-wp-page-head-title
        icon="team"
        :title="__('platform.users.title')"
        help-page="platform.users"
        :subtitle="__('platform.users.subtitle')"
    />

    @if (session('success'))
        <div class="wp-flash wp-flash--success">{{ session('success') }}</div>
    @endif

    <div class="wp-card wp-card-pad wp-stack">
        <label class="wp-label" for="platform-users-search">{{ __('platform.search') }}</label>
        <input id="platform-users-search" type="search" class="wp-input" wire:model.live.debounce.300ms="search"
               placeholder="{{ __('platform.users.search_placeholder') }}" autocomplete="off">

        @if ($users->isEmpty())
            <p class="wp-muted">{{ __('platform.users.empty') }}</p>
        @else
            <ul class="wp-list-plain wp-stack-tight">
                @foreach ($users as $user)
                    <li class="wp-list-row" wire:key="platform-user-{{ $user->id }}">
                        <div class="wp-grow">
                            <p class="wp-text-body">
                                <strong>{{ $user->name }}</strong>
                                @if ($user->is_superuser)
                                    <span class="wp-pill wp-pill--progress">{{ __('platform.users.superuser') }}</span>
                                @elseif (! $user->is_active)
                                    <span class="wp-pill wp-pill--closed">{{ __('platform.users.inactive') }}</span>
                                @endif
                            </p>
                            <p class="wp-muted wp-text-sm">
                                {{ $user->email }} · {{ $user->tenant?->name ?? __('platform.users.no_tenant') }} · {{ $user->role ?? '—' }}
                            </p>
                        </div>
                        @can('setPassword', $user)
                            <button type="button" class="btn btn--ghost btn--sm" wire:click="openPassword({{ $user->id }})">
                                {{ __('platform.users.password.button') }}
                            </button>
                        @endcan
                    </li>
                @endforeach
            </ul>
        @endif
    </div>

    @if ($passwordUserId)
        <x-wp-modal closeMethod="closePassword" aria-labelledby="platform-password-title">
            <form wire:submit="savePassword" class="wp-card wp-modal-card wp-modal-card--form">
                <div class="wp-modal-head wp-modal-head--bordered">
                    <div class="wp-stack-tight">
                        <h2 id="platform-password-title" class="wp-section-title">{{ __('platform.users.password.title') }}</h2>
                        <p class="wp-muted wp-text-sm">{{ __('platform.users.password.hint') }}</p>
                    </div>
                    <x-wp-modal-close wire:click="closePassword" />
                </div>
                <div class="wp-modal-body wp-stack">
                    <div class="wp-field">
                        <label class="wp-label" for="platform-user-password">{{ __('platform.users.password.password') }}</label>
                        <input id="platform-user-password" type="password" class="wp-input" wire:model="password" autocomplete="new-password">
                        @error('password') <p class="wp-error">{{ $message }}</p> @enderror
                    </div>
                    <div class="wp-field">
                        <label class="wp-label" for="platform-user-password-confirmation">{{ __('platform.users.password.confirmation') }}</label>
                        <input id="platform-user-password-confirmation" type="password" class="wp-input" wire:model="password_confirmation" autocomplete="new-password">
                        @error('password_confirmation') <p class="wp-error">{{ $message }}</p> @enderror
                    </div>
                </div>
                <div class="wp-modal-foot">
                    <button type="button" class="btn btn--ghost" wire:click="closePassword">{{ __('common.button.cancel') }}</button>
                    <button type="submit" class="btn btn--primary">{{ __('platform.users.password.save') }}</button>
                </div>
            </form>
        </x-wp-modal>
    @endif
</div>
