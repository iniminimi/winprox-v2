<div
    @class(['wp-stack', 'wp-roster-page--month' => $isMonth])
    data-manual-capture="time-schedule"
    x-data
    x-init="window.wpRosterSheet && window.wpRosterSheet.bind($el, $wire)"
>
    <x-wp-page-head-title
        icon="roster"
        :title="__('time.schedule.title')"
        help-page="time.schedule"
        :subtitle="__('time.schedule.subtitle')"
    >
        <x-slot:toolbar>
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
            <a
                href="{{ route('time.schedule.print', $printParams) }}"
                target="_blank"
                rel="noopener noreferrer"
                class="btn btn--ghost btn--sm"
            >{{ __('common.button.print') }}</a>
        </x-slot:toolbar>
    </x-wp-page-head-title>

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
                <div class="wp-cluster">
                    <div class="wp-filter-cell">
                        <div class="wp-cluster wp-cluster--tight">
                            <button type="button" @class(['btn', 'btn--sm', $isMonth ? 'btn--surface' : 'btn--primary']) wire:click="setView('week')" data-wp-roster-nav>{{ __('time.schedule.view_week') }}</button>
                            <button type="button" @class(['btn', 'btn--sm', $isMonth ? 'btn--primary' : 'btn--surface']) wire:click="setView('month')" data-wp-roster-nav>{{ __('time.schedule.view_month') }}</button>
                        </div>
                    </div>
                    <div class="wp-filter-cell">
                        <label class="wp-check" for="schedule-weekends">
                            <input id="schedule-weekends" type="checkbox" wire:model.live="showWeekends">
                            {{ __('time.schedule.weekends') }}
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
                    <div class="wp-filter-cell">
                        <nav class="wp-pagination" aria-label="{{ $isMonth ? __('time.schedule.month') : __('time.schedule.week') }}">
                            <div class="wp-pagination__pages">
                                <button type="button" class="wp-pagination__control" wire:click="previousWeek" data-wp-roster-nav aria-label="{{ $isMonth ? __('time.schedule.prev_month') : __('time.schedule.prev_week') }}">{{ __('time.schedule.nav_prev') }}</button>
                                <button type="button" class="wp-pagination__page is-active" wire:click="thisWeek" data-wp-roster-nav aria-label="{{ $isMonth ? __('time.schedule.this_month') : __('time.schedule.this_week') }}">{{ $weekLabel }}</button>
                                <button type="button" class="wp-pagination__control" wire:click="nextWeek" data-wp-roster-nav aria-label="{{ $isMonth ? __('time.schedule.next_month') : __('time.schedule.next_week') }}">{{ __('time.schedule.nav_next') }}</button>
                            </div>
                        </nav>
                    </div>
                </div>
                <div class="wp-filter-form__actions">
                    @can('update', \App\Models\PlannedShift::class)
                        <button type="button" class="btn btn--ghost btn--sm" data-wp-roster-save @disabled(! $hasScope)>{{ __('common.button.save') }}</button>
                        <button type="button" class="btn btn--ghost btn--sm" data-wp-roster-day @disabled(! $hasScope)>{{ __('time.schedule.edit_day') }}</button>
                        @unless ($isMonth)
                            <button type="button" class="btn btn--ghost btn--sm" data-wp-roster-copy @disabled(! $hasScope)>{{ __('time.schedule.copy_next') }}</button>
                        @endunless
                    @endcan
                    @can('publish', \App\Models\PlannedShift::class)
                        <button type="button" class="btn btn--primary btn--sm" data-wp-roster-publish data-confirm="{{ $isMonth ? __('time.schedule.publish_confirm_month') : __('time.schedule.publish_confirm') }}" @disabled(! $hasScope)>{{ __('time.schedule.publish') }}</button>
                    @endcan
                    <button type="button" class="btn btn--ghost btn--sm" wire:click="openLegendModal">{{ __('time.schedule.legend_button') }}</button>
                </div>
            </div>
            @if ($locationFilter && $groupUnits !== [])
                <div class="wp-filter-cell wp-time-roster-groups">
                    <span class="wp-filter-inline-label">{{ __('time.schedule.groups_filter') }}</span>
                    <div class="wp-cluster wp-cluster--tight">
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
            @endif
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
                                    @disabled(($block['shift_type_id'] ?? null) !== null)
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
                                    @disabled(($block['shift_type_id'] ?? null) !== null)
                                >
                                <select
                                    class="wp-select"
                                    wire:model="dayEditorBlocks.{{ $i }}.unit_id"
                                    aria-label="{{ __('time.schedule.day_editor.unit') }}"
                                >
                                    <option value="">{{ __('time.schedule.day_editor.no_unit') }}</option>
                                    @foreach ($dayEditorContext['units'] ?? [] as $unit)
                                        <option value="{{ $unit['id'] }}">{{ $unit['code'] }} — {{ $unit['name'] }}</option>
                                    @endforeach
                                </select>
                            @endif

                            <button
                                type="button"
                                class="btn btn--ghost btn--sm"
                                wire:click="removeDayEditorBlock({{ $i }})"
                                aria-label="{{ __('time.schedule.day_editor.remove_block') }}"
                            >×</button>
                        </div>
                    @endforeach

                    <div>
                        <button type="button" class="btn btn--ghost btn--sm" wire:click="addDayEditorBlock">
                            {{ __('time.schedule.day_editor.add_block') }}
                        </button>
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
                    <button type="button" class="btn btn--primary" wire:click="saveDayEditor">
                        {{ __('common.button.save') }}
                    </button>
                    <button type="button" class="btn btn--ghost" wire:click="closeDayEditor">
                        {{ __('common.button.cancel') }}
                    </button>
                </div>
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
