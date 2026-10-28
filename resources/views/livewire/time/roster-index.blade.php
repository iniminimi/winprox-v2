<div
    @class(['wp-stack', 'wp-roster-page', 'wp-roster-page--month' => $isMonth])
    data-manual-capture="time-schedule"
    x-data
    x-init="window.wpRosterSheet && window.wpRosterSheet.bind($el, $wire)"
>
    <x-wp-page-head-title
        icon="time"
        :title="__('time.schedule.title')"
        help-page="time.schedule"
        :subtitle="__('time.schedule.subtitle')"
    >
    </x-wp-page-head-title>
    @php
        $printParams = [
            'week' => $weekStart,
            'view' => $isMonth ? 'month' : 'week',
        ];
        if ($teamFilter) {
            $printParams['team'] = $teamFilter;
        }
        if ($locationFilter) {
            $printParams['location'] = $locationFilter;
            $printParams['ungrouped'] = $showUngrouped ? 1 : 0;
            if ($groupUnitIds !== []) {
                $printParams['groups'] = $groupUnitIds;
            }
        }
        if ($showWeekends) {
            $printParams['weekends'] = 1;
        }
    @endphp

    <div class="wp-no-print">
        @include('partials.wp-time-nav', ['alarmCount' => $alarmCount])
    </div>

    @if (session('time_flash'))
        <div class="wp-flash wp-flash--success">{{ session('time_flash') }}</div>
    @endif
    @if (session('time_flash_error'))
        <div class="wp-flash wp-flash--danger">{{ session('time_flash_error') }}</div>
    @endif

    <div class="wp-card wp-filter-panel wp-time-roster-toolbar wp-no-print">
        <div class="wp-filter-form wp-time-roster-toolbar__form">
            <div class="wp-time-roster-toolbar__bar">
                <div class="wp-toolbar-group wp-toolbar-group--view" role="group" aria-label="{{ __('time.schedule.toolbar.view') }}">
                    <div class="wp-filter-cell">
                        <div class="wp-cluster wp-cluster--tight">
                            <button type="button" @class(['btn', 'btn--sm', 'btn--surface', 'is-active' => ! $isMonth]) wire:click="setView('week')" data-wp-roster-nav>{{ __('time.schedule.view_week') }}</button>
                            <button type="button" @class(['btn', 'btn--sm', 'btn--surface', 'is-active' => $isMonth]) wire:click="setView('month')" data-wp-roster-nav>{{ __('time.schedule.view_month') }}</button>
                        </div>
                    </div>
                    <div class="wp-filter-cell">
                        <label class="wp-switch" for="schedule-weekends">
                            <input id="schedule-weekends" type="checkbox" wire:model.live="showWeekends">
                            <span class="wp-switch__track" aria-hidden="true"></span>
                            <span>{{ __('time.schedule.weekends') }}</span>
                        </label>
                    </div>
                    <div class="wp-filter-cell">
                        <label class="wp-switch" for="schedule-stats">
                            <input id="schedule-stats" type="checkbox" wire:model.live="showStats">
                            <span class="wp-switch__track" aria-hidden="true"></span>
                            <span>{{ __('time.schedule.stats_toggle') }}</span>
                        </label>
                    </div>
                    <div class="wp-filter-cell">
                        <select id="schedule-team" class="wp-select" wire:model.live="teamFilter" aria-label="{{ __('time.filters.team') }}">
                            <option value="">{{ __('time.filters.all_teams') }}</option>
                            @foreach ($teams as $team)
                                <option value="{{ $team['id'] }}">{{ $team['name'] }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="wp-filter-cell">
                        <select id="schedule-location" class="wp-select" wire:model.live="locationFilter" aria-label="{{ __('time.filters.location') }}">
                            <option value="">{{ __('time.filters.all_locations') }}</option>
                            @foreach ($locations as $location)
                                <option value="{{ $location['id'] }}">{{ $location['name'] }}</option>
                            @endforeach
                        </select>
                    </div>
                    @if ($locationFilter && $groupUnits !== [])
                        <div class="wp-filter-cell">
                            <div class="wp-groups-dropdown" x-data="{ open: false }" @click.outside="open = false" @keydown.escape.window="open = false">
                                <button type="button" class="btn btn--sm btn--surface wp-groups-dropdown__trigger" @click="open = !open" :aria-expanded="open.toString()">
                                    <span>{{ __('time.schedule.groups_filter') }} {{ __('time.schedule.groups_count', ['selected' => count($groupUnitIds) + ($showUngrouped ? 1 : 0), 'total' => count($groupUnits) + 1]) }}</span>
                                    <x-wp-icon name="chevron-down" class="wp-icon wp-icon--sm" />
                                </button>
                                <div class="wp-groups-dropdown__panel" x-show="open" x-cloak>
                                    @foreach ($groupUnits as $groupUnit)
                                        <label class="wp-check">
                                            <input type="checkbox" wire:model.live="groupUnitIds" value="{{ $groupUnit['id'] }}">
                                            <span>{{ $groupUnit['code'] }}</span>
                                        </label>
                                    @endforeach
                                    <label class="wp-check">
                                        <input type="checkbox" wire:model.live="showUngrouped">
                                        <span>{{ __('time.schedule.group_ungrouped') }}</span>
                                    </label>
                                </div>
                            </div>
                        </div>
                    @endif
                    <div class="wp-filter-cell">
                        <nav class="wp-pagination" aria-label="{{ $isMonth ? __('time.schedule.month') : __('time.schedule.week') }}">
                            @php
                                $onCurrentPeriod = $isMonth
                                    ? \Carbon\Carbon::parse($weekStart)->format('Y-m') === now()->format('Y-m')
                                    : $weekStart === now()->startOfWeek(\Carbon\Carbon::MONDAY)->toDateString();
                            @endphp
                            <div class="wp-pagination__pages">
                                <button type="button" class="wp-pagination__control" wire:click="previousWeek" data-wp-roster-nav aria-label="{{ $isMonth ? __('time.schedule.prev_month') : __('time.schedule.prev_week') }}"><x-wp-icon name="chevron-left" class="wp-icon wp-icon--sm" /></button>
                                @if ($onCurrentPeriod)
                                    <button type="button" class="wp-pagination__page wp-pagination__page--action is-active wp-time-roster-period" wire:click="thisWeek" data-wp-roster-nav aria-label="{{ $isMonth ? __('time.schedule.this_month') : __('time.schedule.this_week') }}">{{ $weekLabel }}</button>
                                @else
                                    <x-wp-tooltip :text="$isMonth ? __('time.schedule.goto_current_month') : __('time.schedule.goto_current_week')">
                                        <button type="button" class="wp-pagination__page wp-pagination__page--action is-active wp-time-roster-period" wire:click="thisWeek" data-wp-roster-nav aria-label="{{ $isMonth ? __('time.schedule.this_month') : __('time.schedule.this_week') }}">{{ $weekLabel }}</button>
                                    </x-wp-tooltip>
                                @endif
                                <button type="button" class="wp-pagination__control" wire:click="nextWeek" data-wp-roster-nav aria-label="{{ $isMonth ? __('time.schedule.next_month') : __('time.schedule.next_week') }}"><x-wp-icon name="chevron-right" class="wp-icon wp-icon--sm" /></button>
                            </div>
                        </nav>
                        @unless ($onCurrentPeriod)
                            <button type="button" class="btn btn--sm btn--surface" wire:click="thisWeek" data-wp-roster-nav>{{ __('time.schedule.today') }}</button>
                        @endunless
                    </div>
                </div>
                <div class="wp-filter-form__actions">
                    @can('update', \App\Models\PlannedShift::class)
                        <div class="wp-toolbar-group wp-toolbar-group--subtle" role="group" aria-label="{{ __('time.schedule.toolbar.edit') }}">
                            <button type="button" class="btn btn--ghost btn--sm" data-wp-roster-day @disabled(! $hasScope)>{{ __('time.schedule.edit_day') }}</button>
                            @unless ($isMonth)
                                <button type="button" class="btn btn--ghost btn--sm" data-wp-roster-copy @disabled(! $hasScope)>{{ __('time.schedule.copy.button') }}</button>
                            @endunless
                        </div>
                    @endcan
                    @canany(['update', 'publish'], \App\Models\PlannedShift::class)
                        <div class="wp-toolbar-group wp-toolbar-group--action" role="group" aria-label="{{ __('time.schedule.toolbar.save') }}">
                            <span
                                class="wp-pill wp-pill--progress wp-roster-dirty-pill"
                                @if (! $hasUnpublished) hidden @endif
                                data-label-dirty="{{ __('time.schedule.unsaved.title') }}"
                                data-label-unpublished="{{ __('time.schedule.not_published') }}"
                            >{{ $hasUnpublished ? __('time.schedule.not_published') : __('time.schedule.unsaved.title') }}</span>
                            @can('update', \App\Models\PlannedShift::class)
                                <button type="button" class="btn btn--ghost btn--sm" data-wp-roster-save @disabled(! $hasScope)>{{ __('common.button.save') }}</button>
                            @endcan
                            @can('publish', \App\Models\PlannedShift::class)
                                <button type="button" class="btn btn--primary btn--sm" data-wp-roster-publish data-confirm="{{ $isMonth ? __('time.schedule.publish_confirm_month') : __('time.schedule.publish_confirm') }}" @disabled(! $hasScope)>{{ __('time.schedule.publish') }}</button>
                            @endcan
                        </div>
                    @endcanany
                    <div class="wp-toolbar-group wp-toolbar-group--tools" role="group" aria-label="{{ __('time.schedule.toolbar.tools') }}">
                        @can('update', \App\Models\PlannedShift::class)
                            <button type="button" class="btn btn--ghost btn--sm" wire:click="openRestoreModal">{{ __('time.schedule.restore.button') }}</button>
                        @endcan
                        <button type="button" class="btn btn--ghost btn--sm" wire:click="openLegendModal">{{ __('time.schedule.legend_button') }}</button>
                        <a
                            href="{{ route('time.schedule.print', $printParams) }}"
                            target="_blank"
                            rel="noopener noreferrer"
                            class="btn btn--ghost btn--sm"
                        >{{ __('common.button.print') }}</a>
                    </div>
                </div>
            </div>
            {{-- Groepen staan als dropdown in de view-groep hierboven. --}}
        </div>
    </div>

    <div @class(['wp-card', 'wp-card-pad', 'wp-roster-month' => $isMonth]) data-wp-roster-payload="{{ json_encode($gridPayload) }}">
        @if (! $hasScope)
            <p class="wp-muted">{{ __('time.schedule.pick_filter') }}</p>
        @elseif ($snapshot->workers === [])
            <p class="wp-muted">{{ __('time.schedule.empty_workers') }}</p>
        @endif
        <p class="wp-flash wp-flash--danger" data-wp-roster-banner hidden></p>
        <div @class(['wp-roster-grid-shell', 'wp-roster-grid-shell--hidden' => ! $hasScope])>
            <div class="wp-roster-sheet" data-wp-roster-grid wire:ignore></div>
            <div class="wp-roster-menu" data-wp-roster-menu hidden>
                <button type="button" class="wp-roster-menu__item" data-wp-roster-day>
                    {{ __('time.schedule.edit_day') }}
                </button>
                <button type="button" class="wp-roster-menu__item" data-wp-roster-menu-hours hidden>
                    {{ __('time.schedule.attendance.open_hours') }}
                </button>
            </div>
        </div>
    </div>

    @if ($showLegendModal)
        @php
            $workLegend = collect($legendTypes)->filter(fn ($type) => $type->kind->isWork());
            $absenceLegend = collect($legendTypes)->filter(fn ($type) => $type->kind->isAbsence());
            $unitLegend = collect($snapshot->units)->unique('id')->values();
        @endphp
        <x-wp-modal closeMethod="closeLegendModal" aria-labelledby="roster-legend-title">
            <div class="wp-card wp-card-pad wp-stack wp-modal-card wp-modal-card--wide wp-roster-legend-modal-card">
                <div class="wp-modal-head">
                    <h2 id="roster-legend-title" class="wp-section-title">{{ __('time.schedule.legend_title') }}</h2>
                    <x-wp-modal-close wire:click="closeLegendModal" />
                </div>

                <div class="wp-roster-legend-modal">
                    <section class="wp-roster-legend-modal__section">
                        <h3 class="wp-roster-legend-modal__heading">{{ __('time.schedule.legend') }}</h3>
                        @if ($workLegend->isEmpty() && $absenceLegend->isEmpty())
                            <p class="wp-muted">
                                {{ __('time.schedule.legend_empty') }}
                                @can('viewAny', \App\Models\ShiftType::class)
                                    <a href="{{ route('time.shift-types.index') }}">{{ __('time.nav.shift_types') }}</a>
                                @endcan
                            </p>
                        @else
                            <div class="wp-roster-legend-modal__grid">
                                @foreach ($workLegend as $type)
                                    <div class="wp-roster-legend-modal__item">
                                        <span class="wp-roster-color-preview {{ $type->color->hasFill() ? 'wp-roster-cell--'.$type->color->value : '' }}" aria-hidden="true"></span>
                                        <div class="wp-roster-legend-modal__text">
                                            <strong>{{ $type->code }}</strong>
                                            <span class="wp-muted">{{ $type->start_time }}–{{ $type->end_time }}</span>
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        @endif
                    </section>

                    @if ($absenceLegend->isNotEmpty())
                        <section class="wp-roster-legend-modal__section">
                            <h3 class="wp-roster-legend-modal__heading">{{ __('time.schedule.legend_absence') }}</h3>
                            <div class="wp-roster-legend-modal__grid">
                                @foreach ($absenceLegend as $type)
                                    <div class="wp-roster-legend-modal__item">
                                        <span class="wp-roster-color-preview {{ $type->color->hasFill() ? 'wp-roster-cell--'.$type->color->value : '' }}" aria-hidden="true"></span>
                                        <div class="wp-roster-legend-modal__text">
                                            <strong>{{ $type->code }}</strong>
                                            <span class="wp-muted">{{ __('time.schedule.types.kinds.'.$type->kind->value) }}</span>
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        </section>
                    @endif

                    @if ($unitLegend->isNotEmpty())
                        <section class="wp-roster-legend-modal__section">
                            <h3 class="wp-roster-legend-modal__heading">{{ __('time.schedule.legend_units') }}</h3>
                            <div class="wp-roster-legend-modal__grid wp-roster-legend-modal__grid--units">
                                @foreach ($unitLegend as $unit)
                                    <div class="wp-roster-legend-modal__item wp-roster-legend-modal__item--unit">
                                        <div class="wp-roster-legend-modal__text">
                                            <strong>{{ $unit['code'] }}</strong>
                                            <span class="wp-muted">{{ $unit['name'] }}</span>
                                            @if (($unit['location_name'] ?? '') !== '')
                                                <span class="wp-muted wp-roster-legend-modal__location">{{ $unit['location_name'] }}</span>
                                            @endif
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        </section>
                    @endif
                </div>

                <div class="wp-modal-foot">
                    <button type="button" class="btn btn--ghost" wire:click="closeLegendModal">{{ __('common.button.close') }}</button>
                </div>
            </div>
        </x-wp-modal>
    @endif

    @if ($showDayEditor)
        @php
            $absenceTypeIds = collect($dayEditorContext['types'] ?? [])
                ->filter(fn (array $type) => ($type['kind'] ?? 'work') !== 'work')
                ->pluck('id')
                ->map(fn ($id) => (int) $id)
                ->all();
            $dayEditorDateLabel = \Carbon\Carbon::parse($dayEditorContext['date'] ?? now())
                ->locale(app()->getLocale())
                ->translatedFormat('l d/m/Y');
        @endphp
        <x-wp-modal closeMethod="closeDayEditor" aria-labelledby="roster-day-title">
            <div class="wp-card wp-card-pad wp-stack wp-modal-card wp-modal-card--wide">
                <div class="wp-modal-head">
                    <h2 id="roster-day-title" class="wp-section-title">
                        {{ __('time.schedule.day_editor.title', [
                            'name' => $dayEditorContext['worker']['name'] ?? '',
                            'date' => $dayEditorDateLabel,
                        ]) }}
                    </h2>
                    <x-wp-modal-close wire:click="closeDayEditor" />
                </div>

                @if ($dayEditorError !== '')
                    <p class="wp-flash wp-flash--danger">{{ $dayEditorError }}</p>
                @endif

                @if ($dayEditorLocked)
                    <p class="wp-muted">{{ __('time.schedule.day_editor.locked_hint') }}</p>
                @endif

                <div class="wp-stack">
                    @foreach ($dayEditorBlocks as $i => $block)
                        @php
                            $isAbsence = in_array((int) ($block['shift_type_id'] ?? 0), $absenceTypeIds, true);
                        @endphp
                        <div class="wp-roster-day-block" wire:key="day-block-{{ $i }}">
                            <select
                                class="wp-select"
                                wire:model.live="dayEditorBlocks.{{ $i }}.shift_type_id"
                                aria-label="{{ __('time.schedule.day_editor.type') }}"
                                @disabled($dayEditorLocked)
                            >
                                <option value="">{{ __('time.schedule.day_editor.custom_time') }}</option>
                                @foreach ($dayEditorContext['types'] ?? [] as $type)
                                    <option value="{{ $type['id'] }}">
                                        {{ $type['code'] }} — {{ $type['label'] }}
                                        @if (($type['start'] ?? '') !== '' && ($type['end'] ?? '') !== '')
                                            ({{ $type['start'] }}–{{ $type['end'] }})
                                        @endif
                                    </option>
                                @endforeach
                            </select>

                            @if (! $isAbsence)
                                <input
                                    type="text"
                                    class="wp-input"
                                    wire:model="dayEditorBlocks.{{ $i }}.start_time"
                                    aria-label="{{ __('time.schedule.day_editor.start') }}"
                                    placeholder="08:00"
                                    maxlength="5"
                                    inputmode="numeric"
                                    autocomplete="off"
                                    @disabled($dayEditorLocked || ($block['shift_type_id'] ?? null) !== null)
                                >
                                <input
                                    type="text"
                                    class="wp-input"
                                    wire:model="dayEditorBlocks.{{ $i }}.end_time"
                                    aria-label="{{ __('time.schedule.day_editor.end') }}"
                                    placeholder="17:00"
                                    maxlength="5"
                                    inputmode="numeric"
                                    autocomplete="off"
                                    @disabled($dayEditorLocked || ($block['shift_type_id'] ?? null) !== null)
                                >
                                <select
                                    class="wp-select"
                                    wire:model="dayEditorBlocks.{{ $i }}.unit_id"
                                    aria-label="{{ __('time.schedule.day_editor.unit') }}"
                                    @disabled($dayEditorLocked)
                                >
                                    <option value="">{{ __('time.schedule.day_editor.no_unit') }}</option>
                                    @foreach ($dayEditorContext['units'] ?? [] as $unit)
                                        <option value="{{ $unit['id'] }}">{{ $unit['code'] }} — {{ $unit['name'] }}</option>
                                    @endforeach
                                </select>
                            @endif

                            @if (! $dayEditorLocked && ! $isAbsence && ($block['id'] ?? null) !== null)
                                <button
                                    type="button"
                                    class="btn btn--ghost btn--sm"
                                    wire:click="openReplacement([{{ (int) $block['id'] }}])"
                                    title="{{ __('time.schedule.replacement.find') }}"
                                    aria-label="{{ __('time.schedule.replacement.find') }}"
                                >⇄</button>
                            @endif
                            @if ($dayEditorLocked && ($block['id'] ?? null) !== null)
                                @can('correct', \App\Models\PlannedShift::class)
                                    <button
                                        type="button"
                                        class="btn btn--ghost btn--sm"
                                        wire:click="openCorrection({{ (int) $block['id'] }})"
                                    >{{ __('time.schedule.correction.open') }}</button>
                                @endcan
                            @else
                                <button
                                    type="button"
                                    class="btn btn--ghost btn--sm"
                                    wire:click="removeDayEditorBlock({{ $i }})"
                                    aria-label="{{ __('time.schedule.day_editor.remove_block') }}"
                                >×</button>
                            @endif

                            <input
                                type="text"
                                class="wp-input wp-input--description"
                                wire:model="dayEditorBlocks.{{ $i }}.description"
                                aria-label="{{ __('time.schedule.day_editor.description') }}"
                                placeholder="{{ __('time.schedule.day_editor.description') }}"
                                maxlength="500"
                                autocomplete="off"
                                @disabled($dayEditorLocked)
                            >
                        </div>
                    @endforeach

                    @php
                        $savedWorkBlockIds = collect($dayEditorContext['blocks'] ?? [])
                            ->filter(fn (array $b) => ($b['kind'] ?? 'work') === 'work')
                            ->pluck('id')
                            ->map(fn ($id) => (int) $id)
                            ->all();
                    @endphp
                    <div class="wp-cluster wp-cluster--tight">
                        @if ($dayEditorLocked)
                            @can('correct', \App\Models\PlannedShift::class)
                                <button type="button" class="btn btn--ghost btn--sm" wire:click="openCorrectionCreate">
                                    {{ __('time.schedule.correction.add_block') }}
                                </button>
                            @endcan
                        @else
                            <button type="button" class="btn btn--ghost btn--sm" wire:click="addDayEditorBlock">
                                {{ __('time.schedule.day_editor.add_block') }}
                            </button>
                            @if (count($savedWorkBlockIds) > 1)
                                <button
                                    type="button"
                                    class="btn btn--ghost btn--sm"
                                    wire:click="openReplacement(@js($savedWorkBlockIds))"
                                >{{ __('time.schedule.replacement.find_day') }}</button>
                            @endif
                        @endif
                    </div>

                    @if (($dayEditorContext['unplanned_sessions'] ?? []) !== [])
                        <p class="wp-muted">
                            {{ __('time.schedule.day_editor.unplanned_sessions') }}:
                            @foreach ($dayEditorContext['unplanned_sessions'] as $session)
                                <span class="wp-pill">{{ $session['in'] }}–{{ $session['out'] ?? '…' }}</span>
                            @endforeach
                        </p>
                    @endif
                </div>

                <div class="wp-modal-foot">
                    @if ($dayEditorLocked)
                        <button type="button" class="btn btn--ghost" wire:click="closeDayEditor">
                            {{ __('common.button.close') }}
                        </button>
                    @else
                        <button type="button" class="btn btn--primary" wire:click="saveDayEditor">
                            {{ __('common.button.save') }}
                        </button>
                        <button type="button" class="btn btn--ghost" wire:click="closeDayEditor">
                            {{ __('common.button.cancel') }}
                        </button>
                    @endif
                </div>
            </div>
        </x-wp-modal>
    @endif

    @if ($showCorrectionModal)
        @php
            $correctionAbsenceTypeIds = collect($dayEditorContext['types'] ?? [])
                ->filter(fn (array $type) => ($type['kind'] ?? 'work') !== 'work')
                ->pluck('id')
                ->map(fn ($id) => (int) $id)
                ->all();
            $correctionIsAbsence = in_array((int) ($correctionBlock['shift_type_id'] ?? 0), $correctionAbsenceTypeIds, true);
        @endphp
        <x-wp-modal closeMethod="closeCorrection" aria-labelledby="roster-correction-title">
            <div class="wp-card wp-card-pad wp-stack wp-modal-card">
                <div class="wp-modal-head">
                    <h2 id="roster-correction-title" class="wp-section-title">
                        {{ __('time.schedule.correction.title', [
                            'name' => $dayEditorContext['worker']['name'] ?? '',
                        ]) }}
                    </h2>
                    <x-wp-modal-close wire:click="closeCorrection" />
                </div>

                @if ($correctionError !== '')
                    <p class="wp-flash wp-flash--danger">{{ $correctionError }}</p>
                @endif

                <p class="wp-muted">{{ __('time.schedule.correction.hint') }}</p>

                <div class="wp-field">
                    <label class="wp-label" for="roster-correction-date">{{ __('time.schedule.correction.date') }}</label>
                    <input id="roster-correction-date" type="date" class="wp-input" wire:model="correctionBlock.work_date">
                </div>

                <div class="wp-roster-day-block">
                    <select
                        class="wp-select"
                        wire:model.live="correctionBlock.shift_type_id"
                        aria-label="{{ __('time.schedule.day_editor.type') }}"
                    >
                        <option value="">{{ __('time.schedule.day_editor.custom_time') }}</option>
                        @foreach ($dayEditorContext['types'] ?? [] as $type)
                            <option value="{{ $type['id'] }}">
                                {{ $type['code'] }} — {{ $type['label'] }}
                                @if (($type['start'] ?? '') !== '' && ($type['end'] ?? '') !== '')
                                    ({{ $type['start'] }}–{{ $type['end'] }})
                                @endif
                            </option>
                        @endforeach
                    </select>

                    @if (! $correctionIsAbsence)
                        <input
                            type="text"
                            class="wp-input"
                            wire:model="correctionBlock.start_time"
                            aria-label="{{ __('time.schedule.day_editor.start') }}"
                            placeholder="08:00"
                            maxlength="5"
                            inputmode="numeric"
                            autocomplete="off"
                            @disabled(($correctionBlock['shift_type_id'] ?? null) !== null)
                        >
                        <input
                            type="text"
                            class="wp-input"
                            wire:model="correctionBlock.end_time"
                            aria-label="{{ __('time.schedule.day_editor.end') }}"
                            placeholder="17:00"
                            maxlength="5"
                            inputmode="numeric"
                            autocomplete="off"
                            @disabled(($correctionBlock['shift_type_id'] ?? null) !== null)
                        >
                        <select
                            class="wp-select"
                            wire:model="correctionBlock.unit_id"
                            aria-label="{{ __('time.schedule.day_editor.unit') }}"
                        >
                            <option value="">{{ __('time.schedule.day_editor.no_unit') }}</option>
                            @foreach ($dayEditorContext['units'] ?? [] as $unit)
                                <option value="{{ $unit['id'] }}">{{ $unit['code'] }} — {{ $unit['name'] }}</option>
                            @endforeach
                        </select>
                    @endif

                    <input
                        type="text"
                        class="wp-input wp-input--description"
                        wire:model="correctionBlock.description"
                        aria-label="{{ __('time.schedule.day_editor.description') }}"
                        placeholder="{{ __('time.schedule.day_editor.description') }}"
                        maxlength="500"
                        autocomplete="off"
                    >
                </div>

                <div class="wp-field">
                    <label class="wp-label" for="roster-correction-reason">{{ __('time.schedule.correction.reason') }}</label>
                    <input
                        id="roster-correction-reason"
                        type="text"
                        class="wp-input"
                        wire:model="correctionReason"
                        placeholder="{{ __('time.schedule.correction.reason_placeholder') }}"
                        maxlength="500"
                        autocomplete="off"
                    >
                </div>

                <div class="wp-modal-foot">
                    <button type="button" class="btn btn--primary" wire:click="saveCorrection">
                        {{ __('common.button.save') }}
                    </button>
                    @if (! $correctionIsNew)
                        <button
                            type="button"
                            class="btn btn--ghost"
                            wire:click="deleteCorrection"
                            wire:confirm="{{ __('time.schedule.correction.delete_confirm') }}"
                        >{{ __('time.schedule.correction.delete') }}</button>
                    @endif
                    <button type="button" class="btn btn--ghost" wire:click="closeCorrection">
                        {{ __('common.button.cancel') }}
                    </button>
                </div>
            </div>
        </x-wp-modal>
    @endif

    @if ($showReplacementModal)
        <x-wp-modal closeMethod="closeReplacement" aria-labelledby="roster-replacement-title">
            <div class="wp-card wp-card-pad wp-stack wp-modal-card">
                <div class="wp-modal-head">
                    <h2 id="roster-replacement-title" class="wp-section-title">
                        {{ __('time.schedule.replacement.title', [
                            'name' => $dayEditorContext['worker']['name'] ?? '',
                        ]) }}
                    </h2>
                    <x-wp-modal-close wire:click="closeReplacement" />
                </div>

                @if ($replacementError !== '')
                    <p class="wp-flash wp-flash--danger">{{ $replacementError }}</p>
                @endif

                <p class="wp-muted">{{ __('time.schedule.replacement.hint') }}</p>
                @if ($replacementBlockLabels !== [])
                    <p class="wp-cluster wp-cluster--tight">
                        @foreach ($replacementBlockLabels as $label)
                            <span class="wp-pill">{{ $label }}</span>
                        @endforeach
                    </p>
                @endif

                @if ($replacementCandidates === [])
                    <p class="wp-muted">{{ __('time.schedule.replacement.none') }}</p>
                @else
                    <div class="wp-field">
                        <label class="wp-label" for="roster-replacement-worker">{{ __('time.schedule.replacement.candidate') }}</label>
                        <select id="roster-replacement-worker" class="wp-select" wire:model.live="replacementWorkerId">
                            <option value="">{{ __('time.schedule.replacement.choose_candidate') }}</option>
                            @foreach ([1 => 'tier_unit', 2 => 'tier_location', 3 => 'tier_other'] as $tier => $labelKey)
                                @php $tiered = collect($replacementCandidates)->where('tier', $tier); @endphp
                                @if ($tiered->isNotEmpty())
                                    <optgroup label="{{ __('time.schedule.replacement.'.$labelKey) }}">
                                        @foreach ($tiered as $candidate)
                                            <option value="{{ $candidate['worker_id'] }}">
                                                {{ $candidate['name'] }}@if ($candidate['unit_code'] ?? null) ({{ $candidate['unit_code'] }})@endif
                                            </option>
                                        @endforeach
                                    </optgroup>
                                @endif
                            @endforeach
                        </select>
                    </div>
                @endif

                <div class="wp-field">
                    <label class="wp-label" for="roster-replacement-absence">{{ __('time.schedule.replacement.absence_type') }}</label>
                    <select id="roster-replacement-absence" class="wp-select" wire:model="replacementAbsenceTypeId">
                        @foreach (collect($dayEditorContext['types'] ?? [])->filter(fn (array $t) => ($t['kind'] ?? 'work') !== 'work') as $type)
                            <option value="{{ $type['id'] }}">{{ $type['code'] }} — {{ $type['label'] }}</option>
                        @endforeach
                    </select>
                </div>

                <div class="wp-modal-foot">
                    <button
                        type="button"
                        class="btn btn--primary"
                        wire:click="confirmReplacement"
                        @disabled($replacementWorkerId === null || $replacementCandidates === [])
                    >{{ __('time.schedule.replacement.confirm') }}</button>
                    <button type="button" class="btn btn--ghost" wire:click="closeReplacement">
                        {{ __('common.button.cancel') }}
                    </button>
                </div>
            </div>
        </x-wp-modal>
    @endif

    @if ($showRestoreModal)
        <x-wp-modal closeMethod="closeRestoreModal" aria-labelledby="roster-restore-title">
            <div class="wp-card wp-card-pad wp-stack wp-modal-card wp-modal-card--wide">
                <div class="wp-modal-head">
                    <h2 id="roster-restore-title" class="wp-section-title">{{ __('time.schedule.restore.title') }}</h2>
                    <x-wp-modal-close wire:click="closeRestoreModal" />
                </div>

                @if ($restoreError !== '')
                    <p class="wp-flash wp-flash--danger">{{ $restoreError }}</p>
                @endif

                @if ($restoreDone)
                    <p class="wp-flash wp-flash--success">{{ __('time.schedule.restore.success') }}</p>
                    <p class="wp-muted">{{ __('time.schedule.restore.undo_hint') }}</p>
                    <div class="wp-modal-foot">
                        @if ($restoreUndoPointId)
                            <button type="button" class="btn btn--primary" wire:click="undoRestore">
                                {{ __('time.schedule.restore.undo') }}
                            </button>
                        @endif
                        <button type="button" class="btn btn--ghost" wire:click="closeRestoreModal">
                            {{ __('common.button.close') }}
                        </button>
                    </div>
                @elseif ($restoreDetail !== [])
                    <p>
                        <strong>{{ $restoreDetail['when'] }}</strong>
                        — {{ $restoreDetail['source'] }}
                    </p>
                    <p class="wp-muted">
                        {{ __('time.schedule.restore.workers') }}: {{ $restoreDetail['workers'] }}
                    </p>
                    <p class="wp-muted">
                        {{ __('time.schedule.restore.days') }}: {{ $restoreDetail['dates'] }}
                    </p>

                    @if ($restoreDetail['blocks'] !== [])
                        <div class="wp-stack">
                            <p class="wp-flash wp-flash--danger">{{ __('time.schedule.restore.blocks') }}</p>
                            <ul class="wp-stack" role="list">
                                @foreach ($restoreDetail['blocks'] as $block)
                                    <li>{{ $block }}</li>
                                @endforeach
                            </ul>
                        </div>
                    @endif

                    @if ($restoreDetail['warnings'] !== [])
                        <div class="wp-stack">
                            <p>{{ __('time.schedule.restore.warnings') }}</p>
                            <ul class="wp-stack" role="list">
                                @foreach ($restoreDetail['warnings'] as $warning)
                                    <li>{{ $warning }}</li>
                                @endforeach
                            </ul>
                        </div>
                    @endif

                    <div class="wp-stack">
                        <p>{{ __('time.schedule.restore.differences') }}</p>
                        @if ($restoreDetail['differences'] === [])
                            <p class="wp-muted">{{ __('time.schedule.restore.no_difference') }}</p>
                        @else
                            <ul class="wp-stack" role="list">
                                @foreach ($restoreDetail['differences'] as $difference)
                                    <li>{{ $difference }}</li>
                                @endforeach
                            </ul>
                        @endif
                    </div>

                    <div class="wp-modal-foot">
                        @if ($restoreDetail['needs_confirmation'] && ! $restoreDetail['blocked'])
                            <button type="button" class="btn btn--primary" wire:click="confirmRestore">
                                {{ __('time.schedule.restore.confirm') }}
                            </button>
                        @endif
                        <button type="button" class="btn btn--ghost" wire:click="backToRestoreList">
                            {{ __('time.schedule.restore.back') }}
                        </button>
                        <button type="button" class="btn btn--ghost" wire:click="closeRestoreModal">
                            {{ __('common.button.close') }}
                        </button>
                    </div>
                @else
                    @if ($restorePoints === [])
                        <p class="wp-muted">{{ __('time.schedule.restore.empty') }}</p>
                    @else
                        @if ($restorePointsCapped)
                            <p class="wp-muted">{{ __('time.schedule.restore.capped', ['count' => \App\Actions\Time\ListRosterRestorePointsAction::RECENT_LIMIT]) }}</p>
                        @endif
                        <ul class="wp-stack" role="list">
                            @foreach ($restorePoints as $point)
                                <li wire:key="restore-point-{{ $point['id'] }}">
                                    <button type="button" class="btn btn--ghost btn--sm" wire:click="selectRestorePoint({{ $point['id'] }})">
                                        {{ $point['when'] }} — {{ $point['source'] }}
                                    </button>
                                    <p class="wp-muted">
                                        {{ __('time.schedule.restore.workers') }}: {{ $point['workers'] }}
                                        · {{ __('time.schedule.restore.days') }}: {{ $point['dates'] }}
                                    </p>
                                </li>
                            @endforeach
                        </ul>
                    @endif
                    <div class="wp-modal-foot">
                        <button type="button" class="btn btn--ghost" wire:click="closeRestoreModal">
                            {{ __('common.button.close') }}
                        </button>
                    </div>
                @endif
            </div>
        </x-wp-modal>
    @endif

    @if ($showCopyModal)
        <x-wp-modal closeMethod="closeCopyModal" aria-labelledby="roster-copy-title">
            <div class="wp-card wp-card-pad wp-stack wp-modal-card">
                <div class="wp-modal-head">
                    <h2 id="roster-copy-title" class="wp-section-title">{{ __('time.schedule.copy.title') }}</h2>
                    <x-wp-modal-close wire:click="closeCopyModal" />
                </div>

                @if ($copyError !== '')
                    <p class="wp-flash wp-flash--danger">{{ $copyError }}</p>
                @endif

                @if ($copyReport === [])
                    <p class="wp-muted">{{ __('time.schedule.copy.hint') }}</p>
                    <div class="wp-field">
                        <label class="wp-label" for="roster-copy-target">{{ __('time.schedule.copy_target') }}</label>
                        <input id="roster-copy-target" type="date" class="wp-input" wire:model="copyTargetWeek">
                    </div>
                    <div class="wp-field">
                        <label class="wp-label" for="roster-copy-weeks">{{ __('time.schedule.copy.weeks') }}</label>
                        <input id="roster-copy-weeks" type="number" class="wp-input" wire:model="copyWeekCount" min="1" max="26" inputmode="numeric">
                    </div>
                    <div class="wp-modal-foot">
                        <button type="button" class="btn btn--primary" wire:click="copyWeeks">
                            {{ __('time.schedule.copy.submit') }}
                        </button>
                        <button type="button" class="btn btn--ghost" wire:click="closeCopyModal">
                            {{ __('common.button.cancel') }}
                        </button>
                    </div>
                @else
                    <ul class="wp-stack" role="list">
                        @foreach ($copyReport as $week)
                            <li>
                                <strong>{{ \Carbon\Carbon::parse($week['week'])->format('d/m/Y') }}</strong> —
                                @if ($week['status'] === 'ok')
                                    {{ __('time.schedule.copy.week_ok', ['count' => $week['created']]) }}
                                @elseif ($week['status'] === 'occupied')
                                    {{ __('time.schedule.copy.week_occupied') }}
                                @elseif ($week['status'] === 'same_week')
                                    {{ __('time.schedule.copy.week_same') }}
                                @else
                                    {{ $week['message'] ?? __('time.schedule.copy.week_failed') }}
                                @endif
                                @if ($week['skipped'] !== [])
                                    <ul role="list">
                                        @foreach ($week['skipped'] as $skip)
                                            <li class="wp-muted">
                                                {{ __('time.schedule.copy.skipped_absence', [
                                                    'name' => $skip['worker'],
                                                    'date' => \Carbon\Carbon::parse($skip['date'])->format('d/m'),
                                                    'label' => $skip['label'],
                                                ]) }}
                                            </li>
                                        @endforeach
                                    </ul>
                                @endif
                            </li>
                        @endforeach
                    </ul>
                    <div class="wp-modal-foot">
                        <button type="button" class="btn btn--primary" wire:click="closeCopyModal">
                            {{ __('common.button.close') }}
                        </button>
                    </div>
                @endif
            </div>
        </x-wp-modal>
    @endif

    @if ($showUnsavedLeaveModal)
        <x-wp-modal closeMethod="closeUnsavedLeaveModal" aria-labelledby="roster-unsaved-title">
            <div class="wp-card wp-card-pad wp-stack wp-modal-card">
                <div class="wp-modal-head">
                    <h2 id="roster-unsaved-title" class="wp-section-title">{{ __('time.schedule.unsaved.title') }}</h2>
                    <x-wp-modal-close wire:click="closeUnsavedLeaveModal" />
                </div>
                <p class="wp-muted">{{ __('time.schedule.unsaved.body') }}</p>
                <div class="wp-modal-foot">
                    <button type="button" class="btn btn--primary" wire:click="closeUnsavedLeaveModal">
                        {{ __('time.schedule.unsaved.stay') }}
                    </button>
                    <button type="button" class="btn btn--ghost" data-wp-roster-leave-anyway>
                        {{ __('time.schedule.unsaved.leave') }}
                    </button>
                </div>
            </div>
        </x-wp-modal>
    @endif
</div>
