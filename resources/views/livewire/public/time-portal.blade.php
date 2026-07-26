<div class="wp-stack">
    <div class="wp-portal-head">
        <div class="wp-portal-head-top">
            <span class="wp-brand">
                @php
                    $tenant = \App\Support\Tenancy::id() ? \App\Models\Tenant::find(\App\Support\Tenancy::id()) : null;
                    $logoUrl = $tenant ? $tenant->logoPublicUrl() : null;
                @endphp
                @if($logoUrl)
                    <img src="{{ $logoUrl }}" alt="{{ $tenant->name ?? 'Logo' }}" style="max-width: 100px; max-height: 100px; object-fit: contain;">
                @else
                    <img src="{{ asset('images/Winprox_logo_100.png') }}" alt="WinProx" style="max-width: 100px; max-height: 100px; object-fit: contain;">
                @endif
            </span>
            <div class="wp-cluster wp-cluster--tight">
                <x-wp-page-help page="portal.time" />
                @include('partials.wp-portal-theme')
                @include('partials.wp-portal-lang')
            </div>
        </div>
        <x-wp-page-head-title variant="portal" icon="clock" :title="__('time.portal.title')">
            <p class="wp-muted">{{ $clockPointName }}</p>
        </x-wp-page-head-title>
    </div>

    @if ($inactiveReasonKey !== null)
        <div class="wp-card wp-card-pad wp-stack">
            <h2 class="wp-section-title">{{ __('portal.inactive.title') }}</h2>
            <p class="wp-muted">{{ __($inactiveReasonKey) }}</p>
        </div>
    @else
        @if ($flashMessage !== '')
            <div class="wp-flash">{{ $flashMessage }}</div>
        @endif

        @if ($registerOnly || $showRegisterForm)
            <div class="wp-card wp-card-pad wp-stack" data-manual-capture="portal-team-register">
                <h2 class="wp-section-title">{{ __('portal.team.register.title') }}</h2>
                <p class="wp-muted">{{ $registerOnly ? __('portal.team.register.empty_team_hint') : __('portal.team.register.hint') }}</p>

                <form wire:submit="completeOnboarding" class="wp-stack">
                    <div class="wp-field">
                        <label class="wp-label" for="reg_first">{{ __('portal.worker.first_name') }}</label>
                        <input id="reg_first" type="text" class="wp-input" wire:model="first_name" autocomplete="given-name">
                        @error('first_name') <p class="wp-error">{{ $message }}</p> @enderror
                    </div>
                    <div class="wp-field">
                        <label class="wp-label" for="reg_last">{{ __('portal.worker.last_name') }}</label>
                        <input id="reg_last" type="text" class="wp-input" wire:model="last_name" autocomplete="family-name">
                        @error('last_name') <p class="wp-error">{{ $message }}</p> @enderror
                    </div>
                    <div class="wp-field">
                        <label class="wp-label">{{ __('portal.team.register.choose_icon') }}</label>
                        <div class="wp-icon-grid">
                            @foreach (\App\Support\Portal\WorkerIcon::SLUGS as $slug)
                                <button type="button"
                                        wire:key="reg-icon-{{ $slug }}"
                                        wire:click="$set('selected_icon_slug', '{{ $slug }}')"
                                        @class(['wp-icon-tile', 'is-selected' => $selected_icon_slug === $slug])
                                        title="{{ \App\Support\Portal\WorkerIcon::label($slug) }}"
                                        aria-label="{{ \App\Support\Portal\WorkerIcon::label($slug) }}">
                                    <x-wp-worker-icon :slug="$slug" />
                                </button>
                            @endforeach
                        </div>
                        @error('selected_icon_slug') <p class="wp-error">{{ $message }}</p> @enderror
                        @error('identify') <p class="wp-error">{{ $message }}</p> @enderror
                    </div>

                    <div class="wp-stack-tight">
                        <button type="submit" class="btn btn--primary btn--block">{{ __('portal.team.register.submit') }}</button>
                        @unless ($registerOnly)
                            <button type="button" class="btn btn--ghost btn--block btn--sm" wire:click="cancelRegistration">{{ __('common.button.cancel') }}</button>
                        @endunless
                    </div>
                </form>
            </div>
        @elseif ($showIdentify)
            <div class="wp-card wp-card-pad wp-stack" data-manual-capture="portal-team-identify">
                <h2 class="wp-section-title">{{ __('portal.worker.title') }}</h2>
                <p class="wp-muted">{{ __('portal.worker.identify_hint') }}</p>
                <form wire:submit="identifyWorker" class="wp-stack">
                    <div class="wp-field">
                        <label class="wp-label" for="first_name">{{ __('portal.worker.first_name') }}</label>
                        <input id="first_name" type="text" class="wp-input" wire:model="first_name" autocomplete="given-name">
                    </div>
                    <div class="wp-field">
                        <label class="wp-label" for="last_name">{{ __('portal.worker.last_name') }}</label>
                        <input id="last_name" type="text" class="wp-input" wire:model="last_name" autocomplete="family-name">
                    </div>
                    @error('first_name') <p class="wp-error">{{ $message }}</p> @enderror
                    @error('last_name') <p class="wp-error">{{ $message }}</p> @enderror
                    @error('identify') <p class="wp-error">{{ $message }}</p> @enderror
                    <button type="submit" class="btn btn--primary btn--block">{{ __('portal.worker.continue') }}</button>
                </form>
                @if ($allowOpenRegistration)
                    <button type="button" class="btn btn--ghost btn--block btn--sm" wire:click="showRegister">{{ __('portal.team.register.cta') }}</button>
                @endif
            </div>
        @elseif ($showVerify)
            <div class="wp-card wp-card-pad wp-stack">
                <h2 class="wp-section-title">{{ __('portal.worker.title') }}</h2>
                <p class="wp-muted">
                    {{ __('portal.worker.verify_hint') }}
                    @if ($deviceWorker) <strong>{{ $deviceWorker->displayName() }}</strong>@endif
                </p>
                <div class="wp-icon-grid">
                    @foreach (\App\Support\Portal\WorkerIcon::SLUGS as $slug)
                        <button type="button"
                                wire:key="verify-icon-{{ $slug }}"
                                wire:click="$set('sign_in_icon_slug', '{{ $slug }}')"
                                @class(['wp-icon-tile', 'is-selected' => $sign_in_icon_slug === $slug])
                                title="{{ \App\Support\Portal\WorkerIcon::label($slug) }}"
                                aria-label="{{ \App\Support\Portal\WorkerIcon::label($slug) }}">
                            <x-wp-worker-icon :slug="$slug" />
                        </button>
                    @endforeach
                </div>
                @error('sign_in_icon_slug') <p class="wp-error">{{ $message }}</p> @enderror
                <p class="wp-hint">{{ __('portal.worker.attempts_left', ['count' => $remainingAttempts]) }}</p>
                <button type="button" class="btn btn--primary btn--block" wire:click="signInWithIcon" @disabled($sign_in_icon_slug === '')>
                    {{ __('portal.worker.confirm_icon') }}
                </button>
                <button type="button" class="btn btn--ghost btn--block btn--sm" wire:click="signInAsDifferentWorker">
                    {{ __('portal.worker.different_worker') }}
                </button>
            </div>
        @elseif ($iconBlocked)
            <div class="wp-card wp-card-pad wp-stack">
                <p class="wp-error">{{ __('portal.worker.errors.blocked') }}</p>
                <button type="button" class="btn btn--ghost btn--block btn--sm" wire:click="signInAsDifferentWorker">
                    {{ __('portal.worker.different_worker') }}
                </button>
            </div>
        @elseif ($showNoWorkers)
            <div class="wp-card wp-card-pad">
                <p class="wp-muted">{{ __('portal.worker.no_workers') }}</p>
            </div>
        @endif

        @if ($canAct)
            <div class="wp-stack" data-manual-capture="portal-team-signed-in">
                <div class="wp-card wp-card-pad wp-cluster">
                    @if ($verifiedWorker?->field_icon_slug)
                        <div class="wp-icon-tile is-selected" aria-hidden="true" style="pointer-events: none; width: 40px; height: 40px; padding: 0.35rem;">
                            <x-wp-worker-icon :slug="$verifiedWorker->field_icon_slug" />
                        </div>
                    @endif
                    <strong class="wp-text-body">{{ __('common.welcome') }} {{ $verifiedWorker?->displayName() }}</strong>
                </div>

                <div class="wp-portal-worker-actions">
                    @include('partials.wp-portal-sign-out')
                </div>

                @if ($verifiedWorker?->is_teamleader)
                    @include('partials.wp-portal-teamleader-release')
                @endif

                @if ($hasTimeModule)
                    <div class="wp-card wp-card-pad wp-stack">
                        <h2 class="wp-section-title">{{ __('time.portal.clock.title') }}</h2>
                        @if ($openShift === null)
                            <p class="wp-muted">{{ __('time.portal.clock.not_clocked_in') }}</p>
                            <button type="button" class="btn btn--primary btn--block" wire:click="clockIn">
                                {{ __('time.portal.clock.in') }}
                            </button>
                        @else
                            <p class="wp-muted">
                                {{ __('time.portal.clock.clocked_in_since', ['time' => $openShift->clock_in_at->format('H:i')]) }}
                            </p>
                            @if ($openShift->openBreak)
                                <p class="wp-muted">{{ __('time.portal.clock.on_break_since', ['time' => $openShift->openBreak->started_at->format('H:i')]) }}</p>
                                <button type="button" class="btn btn--primary btn--block" wire:click="endBreak">
                                    {{ __('time.portal.clock.end_break') }}
                                </button>
                            @else
                                <div class="wp-cluster">
                                    <button type="button" class="btn btn--surface" wire:click="startBreak">
                                        {{ __('time.portal.clock.start_break') }}
                                    </button>
                                    <button type="button" class="btn btn--primary" wire:click="clockOut">
                                        {{ __('time.portal.clock.out') }}
                                    </button>
                                </div>
                            @endif
                        @endif
                    </div>
                @endif

                <div class="wp-flash wp-flash--muted">{{ __('portal.team.read_only_hint') }}</div>

                <x-wp-page-head-title variant="portal" icon="tasks" :title="__('portal.worker.open_tasks')" />
                <div class="wp-list">
                    @forelse ($tasks as $task)
                        <div class="wp-card wp-card-pad wp-stack" wire:key="time-task-{{ $task->id }}">
                            <div class="wp-cluster">
                                <span class="wp-badge {{ $task->priority->badgeClass() }}">
                                    <x-wp-icon :name="$task->priority->icon()" class="wp-icon wp-icon--sm" />
                                    {{ $task->priority->label() }}
                                </span>
                                <span class="wp-pill wp-pill--{{ $task->status->pillModifier() }}">{{ __($task->status->labelKey()) }}</span>
                                @if ($task->issue?->location)
                                    <span class="wp-muted">{{ $task->issue->location->localizedName() }}@if ($task->issue->unit) &middot; {{ $task->issue->unit->localizedName() }}@endif</span>
                                @endif
                            </div>
                            @if ($task->issue?->isApproved())
                                <p class="wp-text-body">{{ $task->displayDescription() }}</p>
                            @endif
                        </div>
                    @empty
                        <div class="wp-card wp-card-pad"><p class="wp-muted">{{ __('portal.worker.no_open_tasks') }}</p></div>
                    @endforelse
                </div>
            </div>
        @endif
    @endif
</div>
