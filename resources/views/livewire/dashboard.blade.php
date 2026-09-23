<div class="wp-stack" data-manual-capture="dashboard">
    @if ($starterPackSummary)
        <div class="wp-card wp-card-pad">
            <div class="wp-stack">
                <div class="wp-row">
                    <p class="wp-text-body wp-grow"><strong>{{ __('dashboard.starter_pack.result_title') }}</strong></p>
                    @if ($canDismissStarterPackResult)
                        <button type="button"
                                class="btn btn--ghost btn--sm"
                                wire:click="dismissStarterPackResult"
                                wire:loading.attr="disabled"
                                aria-label="{{ __('dashboard.starter_pack.dismiss_result') }}">
                            {{ __('dashboard.starter_pack.dismiss_result') }}
                        </button>
                    @endif
                </div>
                <p class="wp-muted">{{ __('dashboard.starter_pack.result_body') }}</p>
                <p class="wp-muted">{{ __('dashboard.starter_pack.result_own_later') }}</p>
                @error('removeStarterPack')
                    <p class="wp-error">{{ $message }}</p>
                @enderror
                <div class="wp-cluster wp-cluster--tight">
                    @if ($canManageStarterPack)
                        <button type="button" class="btn btn--ghost btn--sm" wire:click="openRemoveStarterPackModal">
                            {{ __('dashboard.starter_pack.remove') }}
                        </button>
                    @endif
                    <a href="{{ $starterPackUnitsHref }}"
                       class="btn btn--primary btn--sm wp-badge-critical">
                        {{ __('dashboard.starter_pack.go_to_units') }}
                    </a>
                </div>
            </div>
        </div>
    @endif

    @if ($showStarterPackChooser)
        <div class="wp-stack-loose">
            <h1 class="wp-page-title">{{ __('dashboard.welcome') }}</h1>
        </div>
        <div class="wp-card wp-card-pad wp-onboarding-card">
            <form wire:submit="applyStarterPack" class="wp-stack">
                <p class="wp-text-body"><strong>{{ __('dashboard.starter_pack.offer_title') }}</strong></p>
                <p class="wp-muted">{{ __('dashboard.starter_pack.intro') }}</p>
                <p class="wp-muted">{{ __('dashboard.starter_pack.intro_detail') }}</p>

                <fieldset class="wp-stack-tight">
                    <legend class="wp-label">{{ __('dashboard.starter_pack.choose_type') }}</legend>
                    @foreach ($starterPackTypes as $type)
                        <label class="wp-check wp-check--boxed">
                            <input type="radio"
                                   name="starterPackType"
                                   value="{{ $type->value }}"
                                   wire:model.live="starterPackType">
                            <span class="wp-grow wp-stack-tight">
                                <p class="wp-text-body"><strong>{{ __($type->labelKey()) }}</strong></p>
                                <p class="wp-muted">{{ __($type->hintKey()) }}</p>
                            </span>
                        </label>
                    @endforeach
                    @error('starterPackType') <p class="wp-error">{{ $message }}</p> @enderror
                </fieldset>

                @if ($starterPackAsksSize)
                    <fieldset class="wp-stack-tight">
                        <legend class="wp-label">{{ __('dashboard.starter_pack.choose_size') }}</legend>
                        @foreach ($starterPackSizes as $size)
                            <label class="wp-check wp-check--boxed">
                                <input type="radio"
                                       name="starterPackSize"
                                       value="{{ $size->value }}"
                                       wire:model.live="starterPackSize">
                                <span>{{ __($size->labelKey()) }}</span>
                            </label>
                        @endforeach
                        @error('starterPackSize') <p class="wp-error">{{ $message }}</p> @enderror
                    </fieldset>
                @endif

                @if ($starterPackPreview)
                    <div class="wp-stack-tight">
                        <p class="wp-text-body"><strong>{{ __('dashboard.starter_pack.preview_teams') }}</strong> — {{ implode(', ', $starterPackPreview['teams']) }}</p>
                        <p class="wp-text-body"><strong>{{ __('dashboard.starter_pack.preview_categories') }}</strong> — {{ implode(', ', $starterPackPreview['categories']) }}</p>
                        <p class="wp-text-body"><strong>{{ __('dashboard.starter_pack.preview_location') }}</strong> — {{ $starterPackPreview['location'] }}</p>
                        <p class="wp-text-body"><strong>{{ __('dashboard.starter_pack.preview_units') }}</strong> — {{ implode(', ', $starterPackPreview['units']) }}</p>
                    </div>
                @endif

                <div class="wp-cluster wp-cluster--tight">
                    <button type="submit" class="btn btn--primary wp-badge-critical" wire:loading.attr="disabled">
                        {{ __('dashboard.starter_pack.create') }}
                    </button>
                    <button type="button" class="btn btn--ghost btn--sm" wire:click="skipStarterPackChooser">
                        {{ __('dashboard.starter_pack.self_start') }}
                    </button>
                </div>
            </form>
        </div>
    @elseif ($onboarding->showTeamsBanner())
        <div class="wp-stack-loose">
            <h1 class="wp-page-title">{{ __('dashboard.welcome') }}</h1>
        </div>
        <x-wp-onboarding-banner stage="teams">
            @if ($canApplyStarterPack)
                <button type="button"
                        class="btn btn--primary btn--sm wp-badge-critical"
                        wire:click="openStarterPackModal">
                    {{ __('dashboard.starter_pack.help_button') }}
                </button>
            @endif
        </x-wp-onboarding-banner>
    @elseif ($onboarding->showCategoriesBanner())
        <x-wp-onboarding-banner stage="categories" />
    @elseif ($onboarding->showLocationsBanner())
        <x-wp-onboarding-banner stage="locations" />
    @elseif ($onboarding->showUnitsBanner())
        <x-wp-onboarding-banner stage="units" />
    @elseif ($onboarding->showClockPointBanner())
        <x-wp-onboarding-banner stage="clock_point" />
    @endif

    @if (! $onboarding->blocksDashboardMain())
        @if (session('register_success'))
            <div class="wp-flash wp-flash--success wp-register-success" role="status">
                <video
                    class="wp-register-success__video"
                    src="{{ asset('video/assistant_task_160.mp4') }}"
                    width="160"
                    height="160"
                    autoplay
                    muted
                    playsinline
                    preload="auto"
                ></video>
                <div class="wp-stack-tight">
                    <p class="wp-text-body"><strong>{{ __('dashboard.register_success.title') }}</strong></p>
                    <p class="wp-muted">{{ __('dashboard.register_success.body') }}</p>
                </div>
            </div>
        @endif

        @if ($intentHub !== null)
            <section class="wp-intent-hub" aria-labelledby="dashboard-intent-heading">
                <div class="wp-intent-tile wp-intent-tile--static wp-intent-tile--green" role="group" aria-labelledby="dashboard-intent-heading">
                    <span class="wp-intent-tile__icon" aria-hidden="true">
                        <x-wp-icon name="team" />
                    </span>
                    <span class="wp-intent-tile__copy">
                        <span class="wp-intent-tile__title" id="dashboard-intent-heading">{{ $intentHub->greeting }}</span>
                        <span class="wp-intent-tile__body">{{ __('dashboard.intent.title') }}</span>
                    </span>
                </div>
                @if (! $intentHub->isEmpty())
                    <div class="wp-intent-hub__grid">
                        @foreach ($intentHub->tiles as $tile)
                            <a href="{{ $tile['href'] }}"
                               class="wp-intent-tile wp-intent-tile--{{ $tile['tone'] }}"
                               wire:key="intent-{{ $tile['key'] }}">
                                <span class="wp-intent-tile__icon" aria-hidden="true">
                                    <x-wp-icon :name="$tile['icon']" />
                                </span>
                                <span class="wp-intent-tile__copy">
                                    <span class="wp-intent-tile__title">{{ __($tile['title']) }}</span>
                                    <span class="wp-intent-tile__body">{{ __($tile['body']) }}</span>
                                </span>
                            </a>
                        @endforeach
                    </div>
                @endif
            </section>
        @endif

        <div class="wp-page-head">
            <div class="wp-grow wp-stack-tight">
                <x-wp-page-head-title
                    icon="dashboard"
                    :title="__('dashboard.title')"
                    help-page="dashboard"
                    :subtitle="__('dashboard.subtitle')"
                />
            </div>
            <div class="wp-cluster">
                @if ($portalBatteryState)
                    <x-wp-trial-battery-capsule :state="$portalBatteryState" />
                @endif
                <a href="{{ route('issues.index', ['create' => 1]) }}" class="btn btn--primary btn--sm">
                    {{ __('dashboard.add_issue') }}
                </a>
                <a href="{{ route('briefing.print') }}" target="_blank" class="btn btn--ghost btn--sm">{{ __('dashboard.briefing_print') }}</a>
            </div>
        </div>

        @php
            $kpiLinks = [
                'locations' => route('locations.index'),
                'units' => route('locations.index'),
                'new_issues' => route('issues.index', ['status' => 'new']),
                'open_tasks' => route('tasks.index'),
                'present_now' => route('time.presence.index'),
                'pending_review' => route('issues.index'),
                'time_attention' => route('time.alarms.index'),
                'pending_absence' => route('time.absence-requests.index'),
                'iot_alarms' => route('iot.index'),
            ];
            $highlightCutoff = now()->subHours(3);
        @endphp

        <div class="wp-kpis">
            @foreach ($stats->kpiTiles() as $kpi)
                <a href="{{ $kpiLinks[$kpi['href_key']] }}"
                   @class(['wp-kpi', 'wp-kpi--'.$kpi['key'], 'wp-kpi--alert' => $kpi['alert'], 'wp-kpi--has-assistant' => $kpi['key'] === 'time_attention'])
                   wire:key="kpi-{{ $kpi['key'] }}">
                    <div class="wp-kpi-body">
                        <span class="wp-kpi-icon" aria-hidden="true">
                            @if ($kpi['key'] === 'time_attention')
                                <video
                                    class="wp-kpi-icon__video"
                                    src="{{ asset('video/assistant_attention.mp4') }}"
                                    width="80"
                                    height="80"
                                    muted
                                    playsinline
                                    preload="auto"
                                    x-data
                                    x-init="
                                        setTimeout(() => {
                                            $el.currentTime = 0;
                                            $el.play().catch(() => {});
                                        }, 1000);
                                    "
                                ></video>
                            @else
                                <x-wp-icon :name="$kpi['icon']" />
                            @endif
                        </span>
                        <div class="wp-kpi-main">
                            <p class="wp-kpi-kicker">{{ __($kpi['label']) }}</p>
                            <p class="wp-kpi-stats">
                                @if ($kpi['key'] === 'pending_absence')
                                    <span class="wp-kpi-value wp-kpi-value--phrase">{{ $stats->pendingAbsenceLabel() }}</span>
                                @else
                                    <span class="wp-kpi-value wp-tabular">{{ $stats->valueFor($kpi['key']) }}</span>
                                    @if ($kpi['meta'])
                                        <span class="wp-kpi-meta">{{ __($kpi['meta']) }}</span>
                                    @endif
                                @endif
                            </p>
                        </div>
                    </div>
                </a>
            @endforeach
        </div>

        <div class="wp-card wp-card-pad wp-stack">
            <div class="wp-row">
                <h2 class="wp-section-title">{{ __('dashboard.recent.title') }}</h2>
                <a href="{{ route('issues.index') }}" class="btn btn--ghost btn--sm">{{ __('dashboard.recent.open_issues') }}</a>
            </div>

            <div class="wp-list wp-list--entity-rows">
                @forelse ($recent as $issue)
                    @include('partials.wp-issue-list-row', [
                        'issue' => $issue,
                        'highlight' => $issue->created_at?->gte($highlightCutoff),
                    ])
                @empty
                    <p class="wp-muted">{{ __('dashboard.recent.empty') }}</p>
                @endforelse
            </div>
        </div>
    @endif

    @if ($showRemoveStarterPackModal)
        <x-wp-modal closeMethod="closeRemoveStarterPackModal" aria-labelledby="starter-pack-remove-title">
            <div class="wp-card wp-card-pad wp-stack wp-modal-card">
                <div class="wp-modal-head">
                    <h2 id="starter-pack-remove-title" class="wp-section-title">{{ __('dashboard.starter_pack.remove_title') }}</h2>
                    <x-wp-modal-close wire:click="closeRemoveStarterPackModal" />
                </div>
                <p class="wp-muted">{{ __('dashboard.starter_pack.remove_text') }}</p>
                <p class="wp-muted">{{ __('dashboard.starter_pack.remove_issues_note') }}</p>
                @error('removeStarterPack')
                    <p class="wp-error">{{ $message }}</p>
                @enderror
                <div class="wp-cluster wp-cluster--tight">
                    <button type="button" class="btn btn--danger" wire:click="removeStarterPack" wire:loading.attr="disabled">
                        {{ __('dashboard.starter_pack.confirm_remove') }}
                    </button>
                    <button type="button" class="btn btn--ghost" wire:click="closeRemoveStarterPackModal">
                        {{ __('common.button.cancel') }}
                    </button>
                </div>
            </div>
        </x-wp-modal>
    @endif
</div>
