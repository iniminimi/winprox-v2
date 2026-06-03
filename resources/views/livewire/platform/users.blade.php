<div class="wp-stack">
    <x-wp-page-head-title
        icon="team"
        :title="__('platform.users.title')"
        help-page="platform.users"
        :subtitle="__('platform.users.subtitle')"
    />

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
                    </li>
                @endforeach
            </ul>
        @endif
    </div>
</div>
