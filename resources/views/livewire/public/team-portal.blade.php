<div class="wp-stack">
    <div class="wp-portal-head">
        <div class="wp-portal-head-top">
            <span class="wp-brand">WinProx</span>
            @include('partials.wp-portal-lang')
        </div>
        <h1 class="wp-page-title">{{ __('portal.team.title') }}</h1>
        <p class="wp-muted">{{ $teamName }}</p>
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

        {{-- ===================== OPEN REGISTRATIE / ONBOARDING ===================== --}}
        @if ($registerOnly || $showRegisterForm)
            <div class="wp-card wp-card-pad wp-stack">
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
                                @php($taken = in_array($slug, $takenIconSlugs, true))
                                <button type="button"
                                        wire:key="reg-icon-{{ $slug }}"
                                        @if (! $taken) wire:click="$set('selected_icon_slug', '{{ $slug }}')" @endif
                                        @class([
                                            'wp-icon-tile',
                                            'is-selected' => $selected_icon_slug === $slug,
                                            'is-disabled' => $taken,
                                        ])
                                        @disabled($taken)
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

        {{-- ========================= IDENTIFICATIE ========================= --}}
        @elseif ($showIdentify)
            <div class="wp-card wp-card-pad wp-stack">
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

        {{-- ====================== ICOONBEVESTIGING ====================== --}}
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

        {{-- ========================== GEBLOKKEERD ========================== --}}
        @elseif ($iconBlocked)
            <div class="wp-card wp-card-pad wp-stack">
                <p class="wp-error">{{ __('portal.worker.errors.blocked') }}</p>
                <button type="button" class="btn btn--ghost btn--block btn--sm" wire:click="signInAsDifferentWorker">
                    {{ __('portal.worker.different_worker') }}
                </button>
            </div>
        @endif

        {{-- ===================== READ-ONLY TAKENOVERZICHT ===================== --}}
        @if ($canAct)
            <div class="wp-row">
                <span class="wp-text-body">{{ __('portal.worker.signed_in_as') }} <strong>{{ $verifiedWorker?->displayName() }}</strong></span>
                <button type="button" class="btn btn--ghost btn--sm" wire:click="signInAsDifferentWorker">{{ __('portal.worker.sign_out') }}</button>
            </div>

            @if ($verifiedWorker?->is_teamleader)
                @include('partials.wp-portal-teamleader-release')
            @endif

            <div class="wp-flash wp-flash--muted">{{ __('portal.team.read_only_hint') }}</div>

            <h2 class="wp-section-title">{{ __('portal.worker.open_tasks') }}</h2>
            <div class="wp-list">
                @forelse ($tasks as $task)
                    <div class="wp-card wp-card-pad wp-stack" wire:key="team-task-{{ $task->id }}">
                        <div class="wp-cluster">
                            <span class="wp-pill wp-pill--{{ $task->status->pillModifier() }}">{{ __($task->status->labelKey()) }}</span>
                            @if ($task->issue?->location)
                                <span class="wp-muted">{{ $task->issue->location->name }}@if ($task->issue->unit) &middot; {{ $task->issue->unit->name }}@endif</span>
                            @endif
                        </div>
                        @if ($task->issue?->isApproved())
                            <p class="wp-text-body">{{ $task->issue->description }}</p>
                        @else
                            <div class="wp-pending-review" data-pending-label="{{ __('portal.pending_review') }}">
                                <p class="wp-text-body">{{ $task->issue?->description }}</p>
                            </div>
                        @endif
                    </div>
                @empty
                    <div class="wp-card wp-card-pad"><p class="wp-muted">{{ __('portal.worker.no_open_tasks') }}</p></div>
                @endforelse
            </div>
        @endif
    @endif
</div>
