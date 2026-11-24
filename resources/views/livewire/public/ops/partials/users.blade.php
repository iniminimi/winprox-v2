<x-wp-page-head-title
    variant="portal"
    icon="team"
    :title="__('ops.menu.users')"
    :subtitle="__('platform.users.subtitle')"
/>

<div class="wp-card wp-card-pad wp-stack">
    <label class="wp-label" for="ops-user-search">{{ __('ops.devices.search') }}</label>
    <input
        id="ops-user-search"
        type="search"
        class="wp-input"
        wire:model.live.debounce.300ms="userSearch"
        placeholder="{{ __('platform.users.search_placeholder') }}"
        autocomplete="off"
    >

    @if ($users->isEmpty())
        <p class="wp-muted">{{ __('platform.users.empty') }}</p>
    @else
        <ul class="wp-list-plain wp-stack-tight">
            @foreach ($users as $user)
                <li class="wp-list-row wp-list-row--end" wire:key="ops-user-{{ $user->id }}">
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
                            {{ $user->email }} · {{ $user->tenant?->name ?? __('platform.users.no_tenant') }}
                        </p>
                    </div>
                    @can('setPassword', $user)
                        <button
                            type="button"
                            class="btn btn--ghost btn--sm"
                            wire:click="openPassword({{ $user->id }})"
                            title="{{ __('platform.users.password.button') }}"
                            aria-label="{{ __('platform.users.password.button') }}"
                        >
                            <x-wp-icon name="key" class="wp-icon" />
                        </button>
                    @endcan
                </li>
            @endforeach
        </ul>
    @endif
</div>

@if ($passwordUserId && $passwordTarget)
    <x-wp-modal closeMethod="closePassword" aria-labelledby="ops-password-title">
        <form wire:submit="savePassword" class="wp-card wp-modal-card wp-modal-card--form">
            <div class="wp-modal-head wp-modal-head--bordered">
                <div class="wp-stack-tight">
                    <h2 id="ops-password-title" class="wp-section-title">{{ __('platform.users.password.title') }}</h2>
                    <p class="wp-muted wp-text-sm">
                        {{ $passwordTarget->name }} · {{ $passwordTarget->email }}
                    </p>
                    <p class="wp-muted wp-text-sm">{{ __('platform.users.password.hint') }}</p>
                </div>
                <x-wp-modal-close wire:click="closePassword" />
            </div>
            <div class="wp-modal-body wp-stack">
                <div class="wp-field">
                    <label class="wp-label" for="ops-user-password">{{ __('platform.users.password.password') }}</label>
                    <input id="ops-user-password" type="password" class="wp-input" wire:model="newPassword" autocomplete="new-password">
                    @error('newPassword') <p class="wp-error">{{ $message }}</p> @enderror
                </div>
                <div class="wp-field">
                    <label class="wp-label" for="ops-user-password-confirmation">{{ __('platform.users.password.confirmation') }}</label>
                    <input id="ops-user-password-confirmation" type="password" class="wp-input" wire:model="newPasswordConfirmation" autocomplete="new-password">
                    @error('newPasswordConfirmation') <p class="wp-error">{{ $message }}</p> @enderror
                </div>
            </div>
            <div class="wp-modal-foot">
                <button type="button" class="btn btn--ghost" wire:click="closePassword">{{ __('common.button.cancel') }}</button>
                <button type="submit" class="btn btn--primary">{{ __('platform.users.password.save') }}</button>
            </div>
        </form>
    </x-wp-modal>
@endif
