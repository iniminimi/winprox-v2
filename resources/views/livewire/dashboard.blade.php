<div class="wp-stack" data-manual-capture="dashboard">
    @if ($checkmate !== null)
        {{-- Checkmate-dashboard (docs/CHECKMATE.md): wie is waar — geen
             facility-tegels, meldingen of starter packs. --}}
        <div class="wp-page-head">
            <div class="wp-grow wp-stack-tight">
                <x-wp-page-head-title
                    icon="dashboard"
                    help-page="checkmate.dashboard"
                    :title="__('dashboard.title')"
                    :subtitle="__('dashboard.checkmate.subtitle')"
                />
            </div>
            <div class="wp-cluster">
                @if ($portalBatteryState)
                    <x-wp-trial-battery-capsule :state="$portalBatteryState" />
                @endif
            </div>
        </div>

        @if ($checkmate->presencePending)
            <div class="wp-card wp-card-pad wp-stack-tight">
                <p class="wp-text-body"><strong>{{ __('dashboard.checkmate.ciao.pending_title') }}</strong></p>
                <p class="wp-muted">{{ __('dashboard.checkmate.ciao.pending_body') }}</p>
                <div class="wp-cluster wp-cluster--tight">
                    <a href="{{ route('settings.index', ['open' => 'presence']) }}#settings-presence" class="btn btn--ghost btn--sm">
                        {{ __('dashboard.checkmate.ciao.pending_cta') }}
                    </a>
                </div>
            </div>
        @elseif ($checkmate->presenceMissingEmployer)
            <div class="wp-card wp-card-pad wp-stack-tight wp-card--prio-pulse">
                <p class="wp-text-body"><strong>{{ __('dashboard.checkmate.ciao.missing_employer_title') }}</strong></p>
                <p class="wp-muted">{{ __('dashboard.checkmate.ciao.missing_employer_body') }}</p>
                <div class="wp-cluster wp-cluster--tight">
                    <a href="{{ route('settings.index', ['open' => 'presence']) }}#settings-presence" class="btn btn--ghost btn--sm">
                        {{ __('dashboard.checkmate.ciao.missing_employer_cta') }}
                    </a>
                </div>
            </div>
        @endif

        @include('partials.wp-time-archive-notices')

        @if ($checkmate->workersMissingSsin !== [])
            <div class="wp-card wp-card-pad wp-stack-tight wp-card--prio-pulse">
                <p class="wp-text-body"><strong>{{ __('dashboard.checkmate.ssin.title') }}</strong></p>
                <p class="wp-muted">{{ __('dashboard.checkmate.ssin.body') }}</p>
                <div class="wp-cluster wp-cluster--tight">
                    @foreach ($checkmate->workersMissingSsin as $missingWorker)
                        <a
                            href="{{ route('team.index', ['section' => 'teams', 'worker' => $missingWorker['id']]) }}"
                            class="btn btn--ghost btn--sm"
                        >{{ $missingWorker['name'] }}</a>
                    @endforeach
                </div>
            </div>
        @endif

        @if ($checkmate->needsWorkers)
            <div class="wp-card wp-card-pad wp-stack-tight">
                <p class="wp-text-body"><strong>{{ __('dashboard.checkmate.onboarding.workers_title') }}</strong></p>
                <p class="wp-muted">{{ __('dashboard.checkmate.onboarding.workers_body') }}</p>
                <div class="wp-cluster wp-cluster--tight">
                    <a href="{{ route('team.index', ['section' => 'teams', 'create_worker' => 1]) }}" class="btn btn--primary btn--sm">
                        {{ __('dashboard.checkmate.onboarding.workers_cta') }}
                    </a>
                </div>
            </div>
        @elseif ($checkmate->needsCustomers)
            <div class="wp-card wp-card-pad wp-stack-tight">
                <p class="wp-text-body"><strong>{{ __('dashboard.checkmate.onboarding.customers_title') }}</strong></p>
                <p class="wp-muted">{{ __('dashboard.checkmate.onboarding.customers_body') }}</p>
                <div class="wp-cluster wp-cluster--tight">
                    <a href="{{ route('customers.index', ['create' => 1]) }}" class="btn btn--primary btn--sm">
                        {{ __('dashboard.checkmate.onboarding.customers_cta') }}
                    </a>
                </div>
            </div>
        @endif

        <div
            x-data="wpIntentTiles"
            data-wp-tile-mode="hide"
            data-wp-hide-method="hideCheckmateTile"
            data-wp-restore-method="restoreCheckmateTile"
            data-wp-close-method="closeHiddenCheckmateTiles"
            @pointerdown="onPointerDown($event)"
            @pointermove.window="onPointerMove($event)"
            @pointerup.window="onPointerUp($event)"
            @pointercancel.window="onPointerUp($event)"
            @click.capture="onClick($event)"
            @contextmenu="onContextMenu($event)"
            @dragstart.prevent
        >
            <div class="wp-kpis" data-wp-intent-grid>
                @if ($showWhatsNew)
                    @include('partials.whats-new-card')
                @endif
                @foreach ($checkmateQuickTiles as $tile)
                    @include('partials.checkmate-dashboard-tile', [
                        'tile' => $tile,
                        'kind' => 'quick',
                        'hidden' => false,
                        'wireKey' => 'cm-action-'.$tile['key'],
                    ])
                @endforeach
                @foreach ($checkmateKpis as $tile)
                    @include('partials.checkmate-dashboard-tile', [
                        'tile' => $tile,
                        'kind' => 'kpi',
                        'hidden' => false,
                        'wireKey' => 'cm-kpi-'.$tile['key'],
                    ])
                @endforeach
                @php
                    $hiddenCheckmateCount = count($hiddenCheckmateTiles);
                @endphp
                <button type="button"
                        @class(['wp-kpi', 'wp-kpi--open_tasks'])
                        @if ($hiddenCheckmateCount > 0) wire:click="openHiddenCheckmateTiles" @endif
                        data-wp-intent-tile="hidden_checkmate_box"
                        data-wp-intent-bin
                        draggable="false"
                        wire:key="cm-hidden-box">
                    <div class="wp-kpi-body">
                        <span class="wp-kpi-icon" aria-hidden="true">
                            <x-wp-icon name="layout-dots" />
                        </span>
                        <div class="wp-kpi-main">
                            <p class="wp-kpi-kicker">{{ __('dashboard.intent.hidden_box.title') }}</p>
                            <p class="wp-kpi-meta wp-kpi-meta--wrap">
                                @if ($hiddenCheckmateCount === 0)
                                    {{ __('dashboard.intent.hidden_box.empty') }}
                                @elseif ($hiddenCheckmateCount === 1)
                                    {{ __('dashboard.intent.hidden_box.one') }}
                                @else
                                    {{ __('dashboard.intent.hidden_box.many', ['count' => $hiddenCheckmateCount]) }}
                                @endif
                            </p>
                        </div>
                    </div>
                </button>
            </div>
            @if ($showHiddenCheckmateTiles && $hiddenCheckmateTiles !== [])
                <div data-wp-intent-hidden-modal>
                    <x-wp-modal closeMethod="closeHiddenCheckmateTiles" aria-labelledby="checkmate-hidden-title">
                        <div class="wp-card wp-card-pad wp-stack wp-modal-card wp-modal-card--wide">
                            <div class="wp-modal-head">
                                <h2 id="checkmate-hidden-title" class="wp-section-title">{{ __('dashboard.intent.hidden_box.title') }}</h2>
                                <x-wp-modal-close wire:click="closeHiddenCheckmateTiles" />
                            </div>
                            <div class="wp-kpis">
                                @foreach ($hiddenCheckmateTiles as $item)
                                    @include('partials.checkmate-dashboard-tile', [
                                        'tile' => $item['tile'],
                                        'kind' => $item['kind'],
                                        'hidden' => true,
                                        'wireKey' => 'cm-hidden-'.$item['tile']['key'],
                                    ])
                                @endforeach
                            </div>
                        </div>
                    </x-wp-modal>
                </div>
            @endif
        </div>

        @if ($checkmatePwaVideoUrl)
            <div class="wp-cluster wp-cluster--center">
                <a href="{{ $checkmatePwaVideoUrl }}" target="_blank" rel="noopener noreferrer" class="btn btn--surface">
                    <x-wp-icon name="video" class="wp-icon" />
                    <span>{{ __('dashboard.checkmate.pwa_video') }}</span>
                </a>
            </div>
        @endif

        <div class="wp-card wp-card-pad wp-stack">
            <div class="wp-row">
                <h2 class="wp-section-title">{{ __('dashboard.checkmate.recent.title') }}</h2>
                <a href="{{ route('time.presence.index') }}" class="btn btn--ghost btn--sm">
                    {{ __('dashboard.checkmate.recent.open') }}
                </a>
            </div>

            <div class="wp-list wp-list--entity-rows">
                @forelse ($checkmate->recentVisits as $visit)
                    @php
                        $place = collect([
                            $visit->location?->customer?->name,
                            $visit->location?->name ?: $visit->location?->formattedAddress(),
                        ])->filter()->join(' · ');
                        $start = $visit->started_at?->timezone(config('app.timezone'));
                        $timeLabel = $visit->isOpen()
                            ? __('dashboard.checkmate.recent.since', ['time' => $start?->format('H:i')])
                            : __('dashboard.checkmate.recent.range', [
                                'start' => $start?->format('d/m H:i'),
                                'end' => $visit->ended_at?->timezone(config('app.timezone'))->format('H:i'),
                            ]);
                    @endphp
                    <a href="{{ $visit->isOpen() ? route('time.presence.index') : route('time.shifts.index') }}"
                       class="wp-issue-row"
                       wire:key="cm-visit-{{ $visit->id }}">
                        <div class="wp-grow wp-stack-tight">
                            <p class="wp-issue-card-title">{{ $visit->worker?->displayName() }}</p>
                            @if ($place !== '')
                                <p class="wp-issue-card-meta">{{ $place }}</p>
                            @endif
                            <p class="wp-issue-card-meta">{{ $timeLabel }}</p>
                        </div>
                        <div class="wp-issue-row-meta">
                            <span class="wp-pill wp-pill--{{ $visit->isOpen() ? 'progress' : 'done' }}">
                                {{ $visit->isOpen() ? __('dashboard.checkmate.recent.running') : __('dashboard.checkmate.recent.done') }}
                            </span>
                        </div>
                    </a>
                @empty
                    <p class="wp-muted">{{ __('dashboard.checkmate.recent.empty') }}</p>
                @endforelse
            </div>
        </div>

        @if ($showCheckmateClockPointQrModal && $checkmateQrClockPoint)
            <x-wp-qr-cluster-modal
                closeMethod="closeCheckmateClockPointQr"
                title-id="checkmate-dashboard-clock-point-qr-modal-title"
                :title="__('common.qr.modal_title')"
                :subtitle="__('common.qr.modal_subtitle', ['name' => $checkmateQrClockPoint->name])"
                :print-url="route('time.clock-points.qr', $checkmateQrClockPoint)"
                :print-label="__('common.qr.print')"
                :formats="collect($checkmateQrPackTemplates)->map(fn ($template) => [
                    'key' => 'cm-dash-'.$checkmateQrClockPoint->id.'-'.$template->value,
                    'title' => __('common.qr.formats.'.$template->value.'.title'),
                    'size' => __('common.qr.formats.'.$template->value.'.size'),
                    'url' => route('time.clock-points.qr-pack', [
                        'clockPoint' => $checkmateQrClockPoint,
                        'template' => $template->value,
                    ]),
                ])->all()"
                :generating="__('time.clock_points.qr.pack.generating')"
                :download-failed="__('time.clock_points.qr.pack.download_failed')"
            >
                <x-slot:email>
                    @include('partials.wp-clock-point-qr-share', [
                        'clockPoint' => $checkmateQrClockPoint,
                        'linkInputId' => 'checkmate-dash-signin-link',
                        'emailInputId' => 'checkmate-dash-qr-mail-email',
                        'emailModel' => 'checkmateQrMailEmail',
                        'sendMethod' => 'sendCheckmateClockPointQrMail',
                        'errorKey' => 'checkmateQrMailEmail',
                        'flash' => $checkmateQrMailFlash,
                    ])
                </x-slot:email>
            </x-wp-qr-cluster-modal>
        @endif
    @else
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
                    <legend class="wp-label">{{ __('dashboard.starter_pack.choose_intent') }}</legend>
                    <label class="wp-check wp-check--boxed">
                        <input type="radio" name="starterPackIntent" value="own_sites" wire:model.live="starterPackIntent">
                        <span class="wp-grow wp-stack-tight">
                            <p class="wp-text-body"><strong>{{ __('starter_pack.intents.own_sites') }}</strong></p>
                            <p class="wp-muted">{{ __('starter_pack.intent_hints.own_sites') }}</p>
                        </span>
                    </label>
                    <label class="wp-check wp-check--boxed">
                        <input type="radio" name="starterPackIntent" value="at_customers" wire:model.live="starterPackIntent">
                        <span class="wp-grow wp-stack-tight">
                            <p class="wp-text-body"><strong>{{ __('starter_pack.intents.at_customers') }}</strong></p>
                            <p class="wp-muted">{{ __('starter_pack.intent_hints.at_customers') }}</p>
                        </span>
                    </label>
                    <label class="wp-check wp-check--boxed">
                        <input type="radio" name="starterPackIntent" value="fleet" wire:model.live="starterPackIntent">
                        <span class="wp-grow wp-stack-tight">
                            <p class="wp-text-body"><strong>{{ __('starter_pack.intents.fleet') }}</strong></p>
                            <p class="wp-muted">{{ __('starter_pack.intent_hints.fleet') }}</p>
                        </span>
                    </label>
                    @error('starterPackIntent') <p class="wp-error">{{ $message }}</p> @enderror
                </fieldset>

                @if ($starterPackIntent === 'at_customers')
                    <fieldset class="wp-stack-tight">
                        <legend class="wp-label">{{ __('dashboard.starter_pack.choose_at_customers') }}</legend>
                        @foreach ($starterPackAtCustomerTypes as $type)
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
                @endif

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
                        @if (($starterPackPreview['customers'] ?? []) !== [])
                            @if (($starterPackPreview['teams'] ?? []) !== [])
                                <p class="wp-text-body"><strong>{{ __('dashboard.starter_pack.preview_teams') }}</strong> — {{ implode(', ', $starterPackPreview['teams']) }}</p>
                            @endif
                            @if (($starterPackPreview['workers'] ?? []) !== [])
                                <p class="wp-text-body"><strong>{{ __('dashboard.starter_pack.preview_workers') }}</strong> — {{ implode(', ', $starterPackPreview['workers']) }}</p>
                            @endif
                            <p class="wp-text-body"><strong>{{ __('dashboard.starter_pack.preview_customers') }}</strong> — {{ implode(', ', $starterPackPreview['customers']) }}</p>
                        @else
                            <p class="wp-text-body"><strong>{{ __('dashboard.starter_pack.preview_teams') }}</strong> — {{ implode(', ', $starterPackPreview['teams']) }}</p>
                            <p class="wp-text-body"><strong>{{ __('dashboard.starter_pack.preview_categories') }}</strong> — {{ implode(', ', $starterPackPreview['categories']) }}</p>
                            <p class="wp-text-body"><strong>{{ __('dashboard.starter_pack.preview_location') }}</strong> — {{ $starterPackPreview['location'] }}</p>
                            <p class="wp-text-body"><strong>{{ __('dashboard.starter_pack.preview_units') }}</strong> — {{ implode(', ', $starterPackPreview['units']) }}</p>
                        @endif
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

        @if (session('time_flash'))
            <div class="wp-flash wp-flash--success">{{ session('time_flash') }}</div>
        @endif

        @include('partials.wp-time-archive-notices')

        @if ($expiringAbsencePhotos > 0)
            @include('partials.wp-absence-photo-expiry-card')
        @endif

        @if ($intentHub !== null)
            <section class="wp-stack wp-dashboard-intent"
                     aria-label="{{ __('dashboard.intent.title') }}"
                     x-data="wpIntentTiles"
                     @pointerdown="onPointerDown($event)"
                     @pointermove.window="onPointerMove($event)"
                     @pointerup.window="onPointerUp($event)"
                     @pointercancel.window="onPointerUp($event)"
                     @click.capture="onClick($event)"
                     @contextmenu="onContextMenu($event)"
                     @dragstart.prevent>
                <x-wp-page-head-title
                    icon="home-greeting"
                    :title="$intentHub->greeting"
                    help-page="dashboard"
                    :subtitle="__('dashboard.intent.title')"
                />

                @if ($showWhatsNew || ! $intentHub->isEmpty() || $canAddDevice)
                    <div class="wp-kpis" data-wp-intent-grid>
                        @if ($showWhatsNew)
                            @include('partials.whats-new-card')
                        @endif
                        @foreach ($intentHub->tiles as $tile)
                            @if ($tile['key'] === 'hidden_box')
                                @php
                                    $hiddenCount = count($intentHub->hiddenTiles);
                                @endphp
                                <button type="button"
                                        @class(['wp-kpi', 'wp-kpi--'.$tile['tone']])
                                        @if ($hiddenCount > 0) wire:click="openHiddenTiles" @endif
                                        data-wp-intent-tile="hidden_box"
                                        data-wp-intent-bin
                                        draggable="false"
                                        wire:key="intent-hidden-box">
                                    <div class="wp-kpi-body">
                                        <span class="wp-kpi-icon" aria-hidden="true">
                                            <x-wp-icon name="layout-dots" />
                                        </span>
                                        <div class="wp-kpi-main">
                                            <p class="wp-kpi-kicker">{{ __('dashboard.intent.hidden_box.title') }}</p>
                                            <p class="wp-kpi-meta wp-kpi-meta--wrap">
                                                @if ($hiddenCount === 0)
                                                    {{ __('dashboard.intent.hidden_box.empty') }}
                                                @elseif ($hiddenCount === 1)
                                                    {{ __('dashboard.intent.hidden_box.one') }}
                                                @else
                                                    {{ __('dashboard.intent.hidden_box.many', ['count' => $hiddenCount]) }}
                                                @endif
                                            </p>
                                        </div>
                                    </div>
                                </button>
                            @elseif (($tile['action'] ?? null) !== null)
                                <button type="button"
                                        wire:click="{{ $tile['action'] }}"
                                        @class(['wp-kpi', 'wp-kpi--'.$tile['tone']])
                                        data-wp-intent-tile="{{ $tile['key'] }}"
                                        data-wp-intent-hideable="1"
                                        draggable="false"
                                        wire:key="intent-{{ $tile['key'] }}">
                                    <div class="wp-kpi-body">
                                        <span class="wp-kpi-icon" aria-hidden="true">
                                            <x-wp-icon :name="$tile['icon']" />
                                        </span>
                                        <div class="wp-kpi-main">
                                            <p class="wp-kpi-kicker">{{ __($tile['title']) }}</p>
                                            <p class="wp-kpi-meta wp-kpi-meta--wrap">{{ __($tile['body']) }}</p>
                                        </div>
                                    </div>
                                </button>
                            @elseif (($tile['href'] ?? null) !== null)
                                <a href="{{ $tile['href'] }}"
                                   @class(['wp-kpi', 'wp-kpi--'.$tile['tone']])
                                   data-wp-intent-tile="{{ $tile['key'] }}"
                                   data-wp-intent-hideable="1"
                                   draggable="false"
                                   wire:key="intent-{{ $tile['key'] }}">
                                    <div class="wp-kpi-body">
                                        <span class="wp-kpi-icon" aria-hidden="true">
                                            <x-wp-icon :name="$tile['icon']" />
                                        </span>
                                        <div class="wp-kpi-main">
                                            <p class="wp-kpi-kicker">{{ __($tile['title']) }}</p>
                                            <p class="wp-kpi-meta wp-kpi-meta--wrap">{{ __($tile['body']) }}</p>
                                        </div>
                                    </div>
                                </a>
                            @else
                                <div @class(['wp-kpi', 'wp-kpi--'.$tile['tone'], 'wp-kpi--arrange-hint'])
                                     data-wp-intent-tile="{{ $tile['key'] }}"
                                     wire:key="intent-{{ $tile['key'] }}">
                                    <div class="wp-kpi-body">
                                        <span class="wp-kpi-icon" aria-hidden="true">
                                            <x-wp-icon :name="$tile['icon']" />
                                        </span>
                                        <div class="wp-kpi-main">
                                            <p class="wp-kpi-kicker">{{ __($tile['title']) }}</p>
                                            <p class="wp-kpi-meta wp-kpi-meta--wrap">{{ __($tile['body']) }}</p>
                                        </div>
                                    </div>
                                </div>
                            @endif
                        @endforeach
                    </div>
                    @if ($canAddDevice)
                        <livewire:add-device />
                    @endif
                @endif

                @if ($showHiddenTiles && $intentHub->hiddenTiles !== [])
                    <div data-wp-intent-hidden-modal>
                        <x-wp-modal closeMethod="closeHiddenTiles" aria-labelledby="intent-hidden-title">
                            <div class="wp-card wp-card-pad wp-stack wp-modal-card wp-modal-card--wide">
                                <div class="wp-modal-head">
                                    <h2 id="intent-hidden-title" class="wp-section-title">{{ __('dashboard.intent.hidden_box.title') }}</h2>
                                    <x-wp-modal-close wire:click="closeHiddenTiles" />
                                </div>
                                <div class="wp-kpis">
                                    @foreach ($intentHub->hiddenTiles as $tile)
                                        @if (($tile['action'] ?? null) !== null)
                                            <button type="button"
                                                    wire:click="{{ $tile['action'] }}"
                                                    @class(['wp-kpi', 'wp-kpi--'.$tile['tone']])
                                                    data-wp-intent-tile="{{ $tile['key'] }}"
                                                    data-wp-intent-hideable="1"
                                                    data-wp-intent-hidden="1"
                                                    draggable="false"
                                                    wire:key="hidden-{{ $tile['key'] }}">
                                                <div class="wp-kpi-body">
                                                    <span class="wp-kpi-icon" aria-hidden="true">
                                                        <x-wp-icon :name="$tile['icon']" />
                                                    </span>
                                                    <div class="wp-kpi-main">
                                                        <p class="wp-kpi-kicker">{{ __($tile['title']) }}</p>
                                                        <p class="wp-kpi-meta wp-kpi-meta--wrap">{{ __($tile['body']) }}</p>
                                                    </div>
                                                </div>
                                            </button>
                                        @else
                                            <a href="{{ $tile['href'] }}"
                                               @class(['wp-kpi', 'wp-kpi--'.$tile['tone']])
                                               data-wp-intent-tile="{{ $tile['key'] }}"
                                               data-wp-intent-hideable="1"
                                               data-wp-intent-hidden="1"
                                               draggable="false"
                                               wire:key="hidden-{{ $tile['key'] }}">
                                                <div class="wp-kpi-body">
                                                    <span class="wp-kpi-icon" aria-hidden="true">
                                                        <x-wp-icon :name="$tile['icon']" />
                                                    </span>
                                                    <div class="wp-kpi-main">
                                                        <p class="wp-kpi-kicker">{{ __($tile['title']) }}</p>
                                                        <p class="wp-kpi-meta wp-kpi-meta--wrap">{{ __($tile['body']) }}</p>
                                                    </div>
                                                </div>
                                            </a>
                                        @endif
                                    @endforeach
                                </div>
                            </div>
                        </x-wp-modal>
                    </div>
                @endif
            </section>
        @endif

        <div @class(['wp-page-head', 'wp-dashboard-overview' => $intentHub !== null])>
            <div class="wp-grow wp-stack-tight">
                <x-wp-page-head-title
                    icon="dashboard"
                    :title="__('dashboard.title')"
                    :help-page="$intentHub === null ? 'dashboard' : null"
                    :subtitle="__('dashboard.subtitle')"
                />
            </div>
            <div class="wp-cluster">
                @if ($portalBatteryState)
                    <x-wp-trial-battery-capsule :state="$portalBatteryState" />
                @endif
                @if ($showIssuesTasksUi ?? true)
                    <a href="{{ route('issues.index', ['create' => 1]) }}" class="btn btn--primary btn--sm">
                        {{ __('dashboard.add_issue') }}
                    </a>
                    <a href="{{ route('briefing.print') }}" target="_blank" class="btn btn--ghost btn--sm">{{ __('dashboard.briefing_print') }}</a>
                @endif
            </div>
        </div>

        @php
            $kpiLinks = [
                'new_issues' => route('issues.index', ['status' => 'new']),
                'open_tasks' => route('tasks.index'),
                'present_now' => route('time.presence.index'),
                'pending_review' => route('issues.index'),
                'time_attention' => route('time.alarms.index'),
                'pending_absence' => route('time.absence-requests.index'),
                'worker_follow_up' => route('workers.index', ['review' => 1]),
                'iot_alarms' => route('iot.index'),
                'staffing_shortfalls' => route('time.schedule.index'),
            ];
            $highlightCutoff = now()->subHours(3);
        @endphp

        <div class="wp-dashboard-kpis"
             x-data="wpIntentTiles"
             data-wp-tile-mode="hide"
             data-wp-hide-method="hideDashboardKpi"
             data-wp-restore-method="restoreDashboardKpi"
             data-wp-close-method="closeHiddenKpis"
             @pointerdown="onPointerDown($event)"
             @pointermove.window="onPointerMove($event)"
             @pointerup.window="onPointerUp($event)"
             @pointercancel.window="onPointerUp($event)"
             @click.capture="onClick($event)"
             @contextmenu="onContextMenu($event)"
             @dragstart.prevent>
        <div class="wp-kpis" data-wp-intent-grid>
            @foreach ($visibleKpis as $kpi)
                <a href="{{ $kpiLinks[$kpi['href_key']] }}"
                   @class(['wp-kpi', 'wp-kpi--'.$kpi['key'], 'wp-kpi--alert' => $kpi['alert'], 'wp-kpi--has-assistant' => $kpi['key'] === 'time_attention'])
                   data-wp-intent-tile="{{ $kpi['key'] }}"
                   data-wp-intent-hideable="1"
                   draggable="false"
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
            @if ($visibleKpis !== [] || $hiddenKpis !== [])
                @php
                    $hiddenKpiCount = count($hiddenKpis);
                @endphp
                <button type="button"
                        @class(['wp-kpi', 'wp-kpi--open_tasks'])
                        @if ($hiddenKpiCount > 0) wire:click="openHiddenKpis" @endif
                        data-wp-intent-tile="hidden_kpi_box"
                        data-wp-intent-bin
                        draggable="false"
                        wire:key="kpi-hidden-box">
                    <div class="wp-kpi-body">
                        <span class="wp-kpi-icon" aria-hidden="true">
                            <x-wp-icon name="layout-dots" />
                        </span>
                        <div class="wp-kpi-main">
                            <p class="wp-kpi-kicker">{{ __('dashboard.intent.hidden_box.title') }}</p>
                            <p class="wp-kpi-meta wp-kpi-meta--wrap">
                                @if ($hiddenKpiCount === 0)
                                    {{ __('dashboard.intent.hidden_box.empty') }}
                                @elseif ($hiddenKpiCount === 1)
                                    {{ __('dashboard.intent.hidden_box.one') }}
                                @else
                                    {{ __('dashboard.intent.hidden_box.many', ['count' => $hiddenKpiCount]) }}
                                @endif
                            </p>
                        </div>
                    </div>
                </button>
            @endif
            @foreach (array_slice($senseAttention ?? [], 0, 8) as $line)
                <a href="{{ $line->url }}"
                   class="wp-kpi wp-kpi--sense wp-kpi--alert"
                   wire:key="sense-attention-{{ $line->sensePointId }}-{{ $loop->index }}">
                    <div class="wp-kpi-body">
                        <span class="wp-kpi-icon" aria-hidden="true">
                            <x-wp-icon name="sensor" />
                        </span>
                        <div class="wp-kpi-main">
                            <p class="wp-kpi-kicker">{{ $line->room }}</p>
                            <p class="wp-kpi-meta wp-kpi-meta--wrap">{{ $line->detail }}</p>
                        </div>
                    </div>
                </a>
            @endforeach
        </div>
        @if ($showHiddenKpis && $hiddenKpis !== [])
            <div data-wp-intent-hidden-modal>
                <x-wp-modal closeMethod="closeHiddenKpis" aria-labelledby="kpi-hidden-title">
                    <div class="wp-card wp-card-pad wp-stack wp-modal-card wp-modal-card--wide">
                        <div class="wp-modal-head">
                            <h2 id="kpi-hidden-title" class="wp-section-title">{{ __('dashboard.intent.hidden_box.title') }}</h2>
                            <x-wp-modal-close wire:click="closeHiddenKpis" />
                        </div>
                        <div class="wp-kpis">
                            @foreach ($hiddenKpis as $kpi)
                                <a href="{{ $kpiLinks[$kpi['href_key']] }}"
                                   @class(['wp-kpi', 'wp-kpi--'.$kpi['key'], 'wp-kpi--alert' => $kpi['alert']])
                                   data-wp-intent-tile="{{ $kpi['key'] }}"
                                   data-wp-intent-hideable="1"
                                   data-wp-intent-hidden="1"
                                   draggable="false"
                                   wire:key="hidden-kpi-{{ $kpi['key'] }}">
                                    <div class="wp-kpi-body">
                                        <span class="wp-kpi-icon" aria-hidden="true">
                                            <x-wp-icon :name="$kpi['icon']" />
                                        </span>
                                        <div class="wp-kpi-main">
                                            <p class="wp-kpi-kicker">{{ __($kpi['label']) }}</p>
                                            <p class="wp-kpi-stats">
                                                @if ($kpi['key'] === 'pending_absence')
                                                    <span class="wp-kpi-value wp-kpi-value--phrase">{{ $stats->pendingAbsenceLabel() }}</span>
                                                @else
                                                    <span class="wp-kpi-value wp-tabular">{{ $stats->valueFor($kpi['key']) }}</span>
                                                @endif
                                            </p>
                                        </div>
                                    </div>
                                </a>
                            @endforeach
                        </div>
                    </div>
                </x-wp-modal>
            </div>
        @endif
        </div>

        @if ($showIssuesTasksUi ?? true)
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
    @endif
    @include('partials.wp-time-manual-clock-in-modal')
</div>
