<div
    @class(['wp-stack', 'wp-roster-page--month' => $isMonth])
    data-manual-capture="time-schedule"
    x-data
    x-init="window.wpRosterSheet && window.wpRosterSheet.bind($el, $wire)"
>
    <x-wp-page-head-title
        :title="__('time.schedule.title')"
        help-page="time.schedule"
        :subtitle="__('time.schedule.subtitle')"
    />

    @include('partials.wp-time-nav', ['alarmCount' => $alarmCount])

    @if (session('time_flash'))
        <div class="wp-flash wp-flash--success">{{ session('time_flash') }}</div>
    @endif
    @if (session('time_flash_error'))
        <div class="wp-flash wp-flash--danger">{{ session('time_flash_error') }}</div>
    @endif

    <div class="wp-card wp-filter-panel wp-time-roster-toolbar">
        <div class="wp-filter-form wp-time-roster-toolbar__form">
            <div class="wp-time-roster-toolbar__bar">
                <div class="wp-cluster">
                    <div class="wp-filter-cell">
                        <span class="wp-filter-inline-label">{{ __('time.schedule.period') }}</span>
                        <div class="wp-cluster wp-cluster--tight">
                            <button type="button" @class(['btn', 'btn--sm', $isMonth ? 'btn--surface' : 'btn--primary']) wire:click="setView('week')">{{ __('time.schedule.view_week') }}</button>
                            <button type="button" @class(['btn', 'btn--sm', $isMonth ? 'btn--primary' : 'btn--surface']) wire:click="setView('month')">{{ __('time.schedule.view_month') }}</button>
                        </div>
                    </div>
                    <div class="wp-filter-cell">
                        <label class="wp-filter-inline-label" for="schedule-team">{{ __('time.filters.team') }}</label>
                        <select id="schedule-team" class="wp-select" wire:model.live="teamFilter">
                            <option value="">{{ __('time.filters.all_teams') }}</option>
                            @foreach ($teams as $team)
                                <option value="{{ $team['id'] }}">{{ $team['name'] }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="wp-filter-cell">
                        <span class="wp-filter-inline-label">{{ $isMonth ? __('time.schedule.month') : __('time.schedule.week') }}</span>
                        <span class="wp-muted">{{ $weekLabel }}</span>
                        <div class="wp-cluster wp-cluster--tight">
                            <button type="button" class="wp-pagination__control" wire:click="previousWeek">{{ $isMonth ? __('time.schedule.prev_month') : __('time.schedule.prev_week') }}</button>
                            <button type="button" class="wp-pagination__control" wire:click="thisWeek">{{ $isMonth ? __('time.schedule.this_month') : __('time.schedule.this_week') }}</button>
                            <button type="button" class="wp-pagination__control" wire:click="nextWeek">{{ $isMonth ? __('time.schedule.next_month') : __('time.schedule.next_week') }}</button>
                        </div>
                    </div>
                </div>
                <div class="wp-filter-form__actions">
                    @can('update', \App\Models\PlannedShift::class)
                        <button type="button" class="btn btn--ghost btn--sm" data-wp-roster-save>{{ __('common.button.save') }}</button>
                        @unless ($isMonth)
                            <button type="button" class="btn btn--ghost btn--sm" data-wp-roster-copy>{{ __('time.schedule.copy_next') }}</button>
                        @endunless
                    @endcan
                    @can('publish', \App\Models\PlannedShift::class)
                        <button type="button" class="btn btn--primary btn--sm" data-wp-roster-publish data-confirm="{{ $isMonth ? __('time.schedule.publish_confirm_month') : __('time.schedule.publish_confirm') }}">{{ __('time.schedule.publish') }}</button>
                    @endcan
                </div>
            </div>
        </div>
    </div>

    <div class="wp-roster-legend" data-wp-roster-legend>
        <span class="wp-filter-inline-label">{{ __('time.schedule.legend') }}</span>
        @forelse ($legendTypes as $type)
            <span class="wp-roster-legend__item">
                <span class="wp-roster-color-preview {{ $type->color->hasFill() ? 'wp-roster-cell--'.$type->color->value : '' }}" aria-hidden="true"></span>
                <strong>{{ $type->code }}</strong>
                <span class="wp-muted">{{ $type->start_time }}–{{ $type->end_time }}</span>
            </span>
        @empty
            <p class="wp-muted">
                {{ __('time.schedule.legend_empty') }}
                @can('viewAny', \App\Models\ShiftType::class)
                    <a href="{{ route('time.shift-types.index') }}">{{ __('time.nav.shift_types') }}</a>
                @endcan
            </p>
        @endforelse
    </div>

    <div @class(['wp-card', 'wp-card-pad', 'wp-roster-month' => $isMonth])>
        @if ($snapshot->workers === [])
            <p class="wp-muted">{{ __('time.schedule.empty_workers') }}</p>
        @endif
        <p class="wp-flash wp-flash--danger" data-wp-roster-banner hidden></p>
        <div class="wp-roster-sheet" data-wp-roster-grid wire:ignore></div>
    </div>
</div>
