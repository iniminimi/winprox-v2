<div class="wp-stack" @if ($roster !== null) wire:poll.visible.15s @endif>
    <div class="wp-portal-head">
        <div class="wp-portal-head-top">
            <span class="wp-brand">
                @php
                    $logoUrl = $tenant ? $tenant->logoPublicUrl() : null;
                @endphp
                @if($logoUrl)
                    <img src="{{ $logoUrl }}" alt="{{ $tenant->name ?? 'Logo' }}">
                @else
                    <img src="{{ asset('images/Winprox_logo_100.png') }}" alt="WinProx">
                @endif
            </span>
            <div class="wp-cluster wp-cluster--tight">
                <x-wp-page-help page="portal.time_roster" />
                @include('partials.wp-portal-theme')
                @include('partials.wp-portal-lang')
            </div>
        </div>
        <x-wp-page-head-title variant="portal" icon="team" :title="__('time.roster.title')">
            <p class="wp-muted">{{ __('time.roster.subtitle') }}</p>
        </x-wp-page-head-title>
    </div>

    @if ($inactiveReasonKey !== null)
        <div class="wp-card wp-card-pad wp-stack">
            <h2 class="wp-section-title">{{ __('portal.inactive.title') }}</h2>
            <p class="wp-muted">{{ __($inactiveReasonKey) }}</p>
        </div>
    @elseif ($showIdentify)
        <div class="wp-card wp-card-pad wp-stack">
            <h2 class="wp-section-title">{{ __('portal.worker.title') }}</h2>
            <p class="wp-muted">{{ __('time.roster.identify_hint') }}</p>
            <form wire:submit="identifyWorker" class="wp-stack">
                <div class="wp-field">
                    <label class="wp-label" for="roster_first_name">{{ __('portal.worker.first_name') }}</label>
                    <input id="roster_first_name" type="text" class="wp-input" wire:model="first_name" autocomplete="given-name">
                </div>
                <div class="wp-field">
                    <label class="wp-label" for="roster_last_name">{{ __('portal.worker.last_name') }}</label>
                    <input id="roster_last_name" type="text" class="wp-input" wire:model="last_name" autocomplete="family-name">
                </div>
                @error('first_name') <p class="wp-error">{{ $message }}</p> @enderror
                @error('last_name') <p class="wp-error">{{ $message }}</p> @enderror
                @error('identify') <p class="wp-error">{{ $message }}</p> @enderror
                <button type="submit" class="btn btn--primary btn--block">{{ __('portal.worker.continue') }}</button>
            </form>
        </div>
    @elseif ($showIcon)
        <div class="wp-card wp-card-pad wp-stack">
            <h2 class="wp-section-title">{{ __('portal.worker.title') }}</h2>
            <p class="wp-muted">
                {{ __('portal.worker.verify_hint') }}
                @if ($rememberedWorker) <strong>{{ $rememberedWorker->displayName() }}</strong>@endif
            </p>
            <div class="wp-icon-grid">
                @foreach (\App\Support\Portal\WorkerIcon::SLUGS as $slug)
                    <button type="button"
                            wire:key="roster-icon-{{ $slug }}"
                            wire:click="$set('sign_in_icon_slug', '{{ $slug }}')"
                            @class(['wp-icon-tile', 'is-selected' => $sign_in_icon_slug === $slug])
                            title="{{ \App\Support\Portal\WorkerIcon::label($slug) }}"
                            aria-label="{{ \App\Support\Portal\WorkerIcon::label($slug) }}">
                        <x-wp-worker-icon :slug="$slug" />
                    </button>
                @endforeach
            </div>
            @error('sign_in_icon_slug') <p class="wp-error">{{ $message }}</p> @enderror
            <button type="button" class="btn btn--primary btn--block" wire:click="signInWithIcon" @disabled($sign_in_icon_slug === '')>
                {{ __('portal.worker.continue') }}
            </button>
            <button type="button" class="btn btn--ghost btn--block btn--sm" wire:click="signInAsDifferentWorker">
                {{ __('portal.worker.different_worker') }}
            </button>
        </div>
    @elseif ($showAck)
        <div class="wp-card wp-card-pad wp-stack">
            <h2 class="wp-section-title">{{ __('time.roster.ack_title') }}</h2>
            <p class="wp-muted">{{ __('time.roster.ack_intro') }}</p>
            <form wire:submit="acknowledgeView" class="wp-stack">
                <label class="wp-check">
                    <input type="checkbox" wire:model="acknowledged">
                    <span>{{ __('time.roster.ack_label') }}</span>
                </label>
                @error('acknowledged') <p class="wp-error">{{ $message }}</p> @enderror
                <button type="submit" class="btn btn--primary btn--block">{{ __('time.roster.ack_submit') }}</button>
                <button type="button" class="btn btn--ghost btn--block btn--sm" wire:click="signInAsDifferentWorker">
                    {{ __('portal.worker.different_worker') }}
                </button>
            </form>
        </div>
    @elseif ($roster !== null)
        <div class="wp-card wp-card-pad wp-stack wp-time-roster">
            <div class="wp-time-roster__head">
                <p class="wp-time-roster__count">{{ __('time.roster.count', ['count' => $roster->count]) }}</p>
                <p class="wp-muted wp-time-roster__updated">{{ now()->format('H:i') }}</p>
            </div>
            @if ($roster->count === 0)
                <p class="wp-muted">{{ __('time.roster.empty') }}</p>
            @else
                @foreach ($roster->byLocation as $locationName => $people)
                    <section class="wp-stack-tight" wire:key="roster-loc-{{ md5((string) $locationName) }}">
                        <h2 class="wp-section-title">{{ $locationName }}</h2>
                        <ul class="wp-time-roster__list">
                            @foreach ($people as $person)
                                <li class="wp-time-roster__person" wire:key="roster-person-{{ $person->workerId }}">
                                    <div class="wp-time-roster__name-row">
                                        <span class="wp-time-roster__name">{{ $person->displayName }}</span>
                                        <span class="wp-pill {{ $person->onBreak ? 'wp-pill--progress' : 'wp-pill--done' }}">
                                            {{ $person->onBreak ? __('time.presence.on_break') : __('time.presence.present') }}
                                        </span>
                                    </div>
                                    <p class="wp-time-roster__meta wp-muted">
                                        {{ __('time.roster.role.'.$person->roleKey) }}
                                        @if ($person->teamName !== '')
                                            · {{ $person->teamName }}
                                        @endif
                                        @if ($person->clockPointName !== '')
                                            · {{ $person->clockPointName }}
                                        @endif
                                        @if ($person->clockedInAt !== '')
                                            · {{ $person->clockedInAt }}
                                        @endif
                                    </p>
                                </li>
                            @endforeach
                        </ul>
                    </section>
                @endforeach
            @endif
            <button type="button" class="btn btn--ghost btn--block btn--sm" wire:click="signInAsDifferentWorker">
                {{ __('portal.worker.different_worker') }}
            </button>
        </div>
    @endif
</div>
