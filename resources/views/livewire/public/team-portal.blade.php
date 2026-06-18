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
                <x-wp-page-help page="portal.team" />
                @include('partials.wp-portal-theme')
                @include('partials.wp-portal-lang')
            </div>
        </div>
        <x-wp-page-head-title variant="portal" icon="team" :title="__('portal.team.title')">
            <p class="wp-muted">{{ $teamName }}</p>
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

        {{-- ========================= IDENTIFICATIE ========================= --}}
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
            <div data-manual-capture="portal-team-signed-in" class="wp-stack">
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

            @if ($showTeamQr)
                <div class="wp-card wp-card-pad wp-stack">
                    <h3 class="wp-section-title">{{ __('portal.teamleader.scan_to_login') }}</h3>
                    <p class="wp-muted">{{ __('portal.teamleader.qr_hint') }}</p>
                    <div class="wp-qr-frame">
                        @php
                            $teamQrUrl = route('public.team-portal', ['token' => $token]);
                            $qrSvg = \App\Support\Qr\TeamQrCode::svg($teamQrUrl, 280);
                            $centerLogoUrl = \App\Support\Qr\QrCenterLogo::publicUrl($tenant);
                        @endphp
                        @include('partials.qr-print-code', ['qrSvg' => $qrSvg, 'centerLogoUrl' => $centerLogoUrl])
                    </div>
                    <button type="button" class="btn btn--ghost btn--block btn--sm" wire:click="$set('showTeamQr', false)">
                        {{ __('common.button.close') }}
                    </button>
                </div>
            @endif

            <div class="wp-flash wp-flash--muted">{{ __('portal.team.read_only_hint') }}</div>

            <x-wp-page-head-title variant="portal" icon="tasks" :title="__('portal.worker.open_tasks')" />
            <div class="wp-list">
                @forelse ($tasks as $task)
                    <div class="wp-card wp-card-pad wp-stack" wire:key="team-task-{{ $task->id }}">
                        <div class="wp-cluster">
                            <span class="wp-badge {{ $task->priority->badgeClass() }}">
                                <x-wp-icon :name="$task->priority->icon()" class="wp-icon wp-icon--sm" />
                                {{ $task->priority->label() }}
                            </span>
                            <span class="wp-pill wp-pill--{{ $task->status->pillModifier() }}">{{ __($task->status->labelKey()) }}</span>
                            @if ($task->issue?->location)
                                <span class="wp-muted">{{ $task->issue->location->name }}@if ($task->issue->unit) &middot; {{ $task->issue->unit->name }}@endif</span>
                            @endif
                            @if ($task->issue?->unit && $task->issue->unit->hasGps())
                                <a href="{{ $task->issue->unit->googleMapsUrl() }}" target="_blank" rel="noopener" class="btn btn--ghost btn--sm" title="{{ __('portal.worker.navigate_to_location') }}">
                                    <x-wp-icon name="map-pin" class="wp-icon--sm" />
                                </a>
                            @endif
                        </div>
                        @if ($task->issue?->isApproved())
                            <p class="wp-text-body">{{ $task->issue->localizedDescription() }}</p>
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
