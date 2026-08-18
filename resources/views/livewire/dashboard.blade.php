<div class="wp-stack" data-manual-capture="dashboard">
    @if ($starterPackSummary)
        <div class="wp-card wp-card-pad">
            <div class="wp-stack">
                <p class="wp-text-body"><strong>{{ __('dashboard.starter_pack.result_title') }}</strong></p>
                <p class="wp-muted">{{ __('dashboard.starter_pack.result_type', ['type' => __($starterPackSummary->type->labelKey())]) }}</p>
                <div class="wp-stack-tight">
                    <p class="wp-text-body"><strong>{{ __('dashboard.starter_pack.result_teams') }}</strong> — {{ implode(', ', $starterPackSummary->teamNames) }}</p>
                    <p class="wp-text-body"><strong>{{ __('dashboard.starter_pack.result_categories') }}</strong> — {{ implode(', ', $starterPackSummary->categoryNames) }}</p>
                    @if ($starterPackSummary->locationName !== '')
                        <p class="wp-text-body"><strong>{{ __('dashboard.starter_pack.result_location') }}</strong> — {{ $starterPackSummary->locationName }}</p>
                    @endif
                    <p class="wp-text-body"><strong>{{ __('dashboard.starter_pack.result_units') }}</strong> — {{ implode(', ', $starterPackSummary->unitNames) }}</p>
                </div>
                <p class="wp-muted">{{ __('dashboard.starter_pack.rename_note') }} {{ __('dashboard.starter_pack.issues_note') }}</p>
                <p class="wp-text-body wp-error"><strong>{{ __('dashboard.starter_pack.result_next') }}</strong></p>
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

    @if ($onboarding->showTeamsBanner())
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

    @if ($onboarding->showWelcomeGuide)
        <div class="wp-stack-loose">
            <h1 class="text-4xl font-bold text-gray-900">{{ __('dashboard.welcome') }}</h1>
        </div>

        <div class="wp-card wp-card-pad">
            <div class="wp-stack">
                <h2 class="wp-section-title">{{ __('manual.getting_started.label') }}</h2>
                <p class="wp-text-body"><strong>{{ __('manual.getting_started.title') }}</strong></p>
                <p class="wp-muted">{{ __('manual.getting_started.intro') }}</p>

                <div class="wp-stack-tight">
                    <div class="wp-stack-tight">
                        <p class="wp-text-body"><strong>{{ __('manual.step_1_title') }}</strong></p>
                        <p class="wp-muted">{{ __('manual.step_1_text') }}</p>
                    </div>
                    <div class="wp-stack-tight">
                        <p class="wp-text-body"><strong>{{ __('manual.step_2_title') }}</strong></p>
                        <p class="wp-muted">{{ __('manual.step_2_text') }}</p>
                    </div>
                    <div class="wp-stack-tight">
                        <p class="wp-text-body"><strong>{{ __('manual.step_3_title') }}</strong></p>
                        <p class="wp-muted">{{ __('manual.step_3_text_time') }}</p>
                    </div>
                    <div class="wp-stack-tight">
                        <p class="wp-text-body"><strong>{{ __('manual.step_4_title') }}</strong></p>
                        <p class="wp-muted">{{ __('manual.step_4_text') }}</p>
                    </div>
                    <div class="wp-stack-tight">
                        <p class="wp-text-body"><strong>{{ __('manual.step_5_title') }}</strong></p>
                        <p class="wp-muted">{{ __('manual.step_5_text') }}</p>
                    </div>
                </div>
            </div>
        </div>
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
                        <div class="wp-kpi-main">
                            <p class="wp-kpi-kicker">{{ __($kpi['label']) }}</p>
                            <p class="wp-kpi-stats">
                                <span class="wp-kpi-value wp-tabular">{{ $stats->valueFor($kpi['key']) }}</span>
                                @if ($kpi['meta'])
                                    <span class="wp-kpi-meta">{{ __($kpi['meta']) }}</span>
                                @endif
                            </p>
                        </div>
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
                    </div>
                </a>
            @endforeach
        </div>

        @php
            $showHealthWidget = ! $health->isHealthy() && $health->totalChecks > 0;
        @endphp

        <div @class(['wp-dashboard-widgets', 'wp-dashboard-widgets--single' => ! $showHealthWidget])>
            @if ($showHealthWidget)
                <a href="{{ route('health.index') }}" class="wp-dashboard-widget wp-health-widget wp-card wp-card-pad" wire:key="health-widget">
                    <div class="wp-health-widget__body">
                        <x-wp-health-donut
                            size="sm"
                            :percent-complete="$health->percentComplete()"
                            :incomplete-fraction="$health->incompleteFraction()"
                        />
                        <div class="wp-stack-tight wp-grow">
                            <p class="wp-kpi-kicker">{{ __('health.widget.kicker') }}</p>
                            <p class="wp-dashboard-widget__title">{{ __('health.widget.title') }}</p>
                            <p class="wp-muted">{{ trans_choice('health.widget.issues', $health->issueCount, ['count' => $health->issueCount]) }}</p>
                        </div>
                        <x-wp-icon name="arrow-right" class="wp-health-widget__chevron" />
                    </div>
                </a>
            @endif

            @php
                $trafficScanTotal = collect($topScannedUnits)->sum(static fn ($row) => $row->scanCount);
            @endphp

            <div
                class="wp-dashboard-widget wp-traffic-widget wp-card wp-card-pad"
                wire:key="traffic-widget"
                @if ($topScannedUnits !== []) x-data="{ open: false }" @endif
            >
                @if ($topScannedUnits !== [])
                    <button
                        type="button"
                        class="wp-traffic-widget-toggle"
                        @click="open = !open"
                        :aria-expanded="open ? 'true' : 'false'"
                        aria-controls="wp-traffic-widget-panel"
                    >
                        <div class="wp-grow wp-stack-tight">
                            <p class="wp-kpi-kicker">{{ __('dashboard.traffic.kicker') }}</p>
                            <p class="wp-dashboard-widget__title">{{ __('dashboard.traffic.title') }}</p>
                            <p class="wp-muted" x-show="!open">
                                {{ __('dashboard.traffic.collapsed_summary', [
                                    'units' => count($topScannedUnits),
                                    'scans' => $trafficScanTotal,
                                ]) }}
                            </p>
                            <p class="wp-muted" x-show="open" x-cloak>{{ __('dashboard.traffic.subtitle') }}</p>
                        </div>
                        <x-wp-icon name="chevron-down" class="wp-disclosure-chevron" x-bind:class="{ 'is-open': open }" />
                    </button>
                    <div id="wp-traffic-widget-panel" class="wp-disclosure-panel" x-show="open" x-cloak>
                        <div class="wp-list wp-list--entity-rows wp-traffic-widget__list">
                            @foreach ($topScannedUnits as $row)
                                <a href="{{ $row->detailUrl }}" class="wp-traffic-row" wire:key="traffic-unit-{{ $row->unitId }}">
                                    <div class="wp-grow wp-stack-tight">
                                        <p class="wp-issue-card-title">{{ $row->unitName }}</p>
                                        <p class="wp-issue-card-meta">{{ $row->locationName }}</p>
                                    </div>
                                    <div class="wp-cluster">
                                        <span class="wp-pill wp-pill--closed wp-tabular">{{ trans_choice('dashboard.traffic.scans', $row->scanCount, ['count' => $row->scanCount]) }}</span>
                                        <x-wp-icon name="arrow-right" class="wp-traffic-row__chevron" />
                                    </div>
                                </a>
                            @endforeach
                        </div>
                    </div>
                @else
                    <div class="wp-stack-tight">
                        <p class="wp-kpi-kicker">{{ __('dashboard.traffic.kicker') }}</p>
                        <p class="wp-dashboard-widget__title">{{ __('dashboard.traffic.title') }}</p>
                        <p class="wp-muted">{{ __('dashboard.traffic.subtitle') }}</p>
                    </div>
                    <p class="wp-muted wp-traffic-widget__empty">{{ __('dashboard.traffic.empty') }}</p>
                @endif
            </div>
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

    @if ($showStarterPackModal)
        <x-wp-modal closeMethod="closeStarterPackModal" aria-labelledby="starter-pack-title">
            <form wire:submit="applyStarterPack" class="wp-card wp-card-pad wp-stack wp-modal-card">
                <div class="wp-modal-head">
                    <h2 id="starter-pack-title" class="wp-section-title">{{ __('dashboard.starter_pack.modal_title') }}</h2>
                    <x-wp-modal-close wire:click="closeStarterPackModal" />
                </div>

                <p class="wp-muted">{{ __('dashboard.starter_pack.intro') }}</p>
                <p class="wp-text-body">{{ __('dashboard.starter_pack.will_create') }} {{ __('dashboard.starter_pack.will_create_items') }}</p>
                <p class="wp-muted">{{ __('dashboard.starter_pack.rename_note') }}</p>
                <p class="wp-muted">{{ __('dashboard.starter_pack.issues_note') }}</p>

                <fieldset class="wp-stack-tight">
                    <legend class="wp-label">{{ __('dashboard.starter_pack.choose_type') }}</legend>
                    @foreach ($starterPackTypes as $type)
                        <label class="wp-check wp-check--boxed">
                            <input type="radio"
                                   name="starterPackType"
                                   value="{{ $type->value }}"
                                   wire:model.live="starterPackType">
                            <span>{{ __($type->labelKey()) }}</span>
                        </label>
                    @endforeach
                    @error('starterPackType') <p class="wp-error">{{ $message }}</p> @enderror
                </fieldset>

                @if ($starterPackPreview)
                    <div class="wp-stack-tight">
                        <p class="wp-text-body"><strong>{{ __('dashboard.starter_pack.preview_teams') }}</strong> — {{ implode(', ', $starterPackPreview['teams']) }}</p>
                        <p class="wp-text-body"><strong>{{ __('dashboard.starter_pack.preview_categories') }}</strong> — {{ implode(', ', $starterPackPreview['categories']) }}</p>
                        <p class="wp-text-body"><strong>{{ __('dashboard.starter_pack.preview_location') }}</strong> — {{ $starterPackPreview['location'] }}</p>
                        <p class="wp-text-body"><strong>{{ __('dashboard.starter_pack.preview_units') }}</strong> — {{ implode(', ', $starterPackPreview['units']) }}</p>
                    </div>
                @endif

                <div class="wp-cluster wp-cluster--tight">
                    <button type="submit" class="btn btn--primary" wire:loading.attr="disabled">
                        {{ __('dashboard.starter_pack.create') }}
                    </button>
                    <button type="button" class="btn btn--ghost" wire:click="closeStarterPackModal">
                        {{ __('common.button.cancel') }}
                    </button>
                </div>
            </form>
        </x-wp-modal>
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
