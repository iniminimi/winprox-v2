<div class="wp-stack" data-manual-capture="time-shifts">
    <x-wp-page-head-title
        :title="__('time.title')"
        help-page="time.shifts"
        :subtitle="__('time.shifts.subtitle')"
    />

    @include('partials.wp-time-nav', ['alarmCount' => $alarmCount])

    @if (session('time_flash'))
        <div class="wp-flash wp-flash--success">{{ session('time_flash') }}</div>
    @endif

    <div class="wp-card wp-filter-panel wp-time-shifts-toolbar">
        <div class="wp-filter-form wp-time-shifts-toolbar__form">
            <div class="wp-filter-form__row wp-time-shifts-toolbar__filters">
                <div class="wp-filter-cell">
                    <label class="wp-filter-inline-label" for="shifts-from">{{ __('time.filters.from') }}</label>
                    <input id="shifts-from" type="date" class="wp-input" wire:model="from">
                </div>
                <div class="wp-filter-cell">
                    <label class="wp-filter-inline-label" for="shifts-to">{{ __('time.filters.to') }}</label>
                    <input id="shifts-to" type="date" class="wp-input" wire:model="to">
                </div>
                <div class="wp-filter-cell">
                    <label class="wp-filter-inline-label" for="shifts-team">{{ __('time.filters.team') }}</label>
                    <select id="shifts-team" class="wp-select" wire:model="teamFilter">
                        <option value="">{{ __('time.filters.all_teams') }}</option>
                        @foreach ($teams as $team)
                            <option value="{{ $team->id }}">{{ $team->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="wp-filter-cell">
                    <label class="wp-filter-inline-label" for="shifts-worker">{{ __('time.filters.worker') }}</label>
                    <select id="shifts-worker" class="wp-select" wire:model="workerFilter">
                        <option value="">{{ __('time.filters.all_workers') }}</option>
                        @foreach ($workers as $worker)
                            <option value="{{ $worker->id }}">{{ $worker->displayName() }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="wp-filter-cell">
                    <label class="wp-filter-inline-label" for="shifts-clock-point">{{ __('time.filters.clock_point') }}</label>
                    <select id="shifts-clock-point" class="wp-select" wire:model="clockPointFilter">
                        <option value="">{{ __('time.filters.all_clock_points') }}</option>
                        @foreach ($clockPoints as $clockPoint)
                            <option value="{{ $clockPoint->id }}">{{ $clockPoint->name }}</option>
                        @endforeach
                    </select>
                </div>
            </div>
            <div class="wp-filter-form__actions">
                <button type="button" class="btn btn--primary btn--sm" wire:click="applyFilters">{{ __('time.filters.apply') }}</button>
                <a href="{{ $exportUrl }}" class="btn btn--surface btn--sm">{{ __('time.export.button') }}</a>
                <a href="{{ $printUrl }}" target="_blank" rel="noopener noreferrer" class="btn btn--surface btn--sm">{{ __('time.print.button') }}</a>
            </div>
        </div>
    </div>

    <div class="wp-list">
        @forelse ($shifts as $shift)
            <div class="wp-card wp-card-pad wp-cluster wp-cluster--spread" wire:key="shift-{{ $shift->id }}">
                <div>
                    <strong>{{ $shift->worker?->displayName() }}</strong>
                    <p class="wp-muted wp-text-sm">
                        {{ $shift->clock_in_at->format('d-m-Y') }}
                        &middot; {{ $shift->clock_in_at->format('H:i') }}
                        @if ($shift->clock_out_at)
                            – {{ $shift->clock_out_at->format('H:i') }}
                        @endif
                        &middot; {{ $shift->team?->name }}
                    </p>
                    <p class="wp-muted wp-text-sm">
                        {{ __('time.shifts.break_minutes', ['duration' => \App\Support\Time\WorkDurationFormatter::format($shift->total_break_minutes)]) }}
                        &middot; {{ __('time.shifts.worked', ['duration' => \App\Support\Time\WorkDurationFormatter::format($shift->netWorkMinutes())]) }}
                    </p>
                    <p class="wp-muted wp-text-sm">
                        {{ __('time.shifts.clocked_in_at', ['name' => $shift->clockInClockPoint?->name ?? '—']) }}
                        @if ($shift->clockOutClockPoint)
                            &middot; {{ __('time.shifts.clocked_out_at', ['name' => $shift->clockOutClockPoint->name]) }}
                        @endif
                    </p>
                    @if ($shift->taskLogs->isNotEmpty())
                        <p class="wp-muted wp-text-sm">{{ __('time.shifts.tasks_heading') }}</p>
                        <ul class="wp-muted wp-text-sm">
                            @foreach ($shift->taskLogs as $log)
                                <li wire:key="shift-{{ $shift->id }}-task-{{ $log->id }}">
                                    {{ $log->task?->displayDescription() ?: __('time.shifts.task_unknown') }}
                                    @if ($log->ended_at)
                                        &middot; {{ __('time.shifts.task_duration', ['minutes' => $log->durationMinutes()]) }}
                                    @else
                                        &middot; {{ __('time.shifts.task_open') }}
                                    @endif
                                </li>
                            @endforeach
                        </ul>
                    @endif
                </div>
                <div class="wp-cluster">
                    @if ($shift->status === \App\Enums\WorkShiftStatus::ForceClosed && $shift->clock_out_source === \App\Enums\ClockSource::Auto)
                        <span class="wp-pill wp-pill--closed">{{ __('time.status.auto_closed') }}</span>
                    @else
                        <span class="wp-pill">{{ __('time.status.'.$shift->status->value) }}</span>
                    @endif
                    @if ($shift->status->isOpen())
                        <button type="button" class="btn btn--ghost btn--sm" wire:click="openForceClose({{ $shift->id }})">
                            {{ __('time.presence.force_close') }}
                        </button>
                    @elseif (auth()->user()?->can('correct', $shift))
                        <button type="button" class="btn btn--ghost btn--sm" wire:click="openCorrection({{ $shift->id }})">
                            {{ __('time.corrections.button') }}
                        </button>
                    @endif
                </div>
            </div>
        @empty
            <div class="wp-card wp-card-pad"><p class="wp-muted">{{ __('time.shifts.empty') }}</p></div>
        @endforelse
    </div>

    {{ $shifts->links() }}

    @if ($showCorrectionModal)
        <x-wp-modal closeMethod="closeCorrection" aria-labelledby="shift-correction-title">
            <form wire:submit="saveCorrection" class="wp-card wp-card-pad wp-stack wp-modal-card">
                <div class="wp-modal-head">
                    <h2 id="shift-correction-title" class="wp-h2">{{ __('time.corrections.title') }}</h2>
                    <x-wp-modal-close wire:click="closeCorrection" />
                </div>
                <p class="wp-muted wp-text-sm">{{ __('time.corrections.subtitle') }}</p>
                <div class="wp-field">
                    <label class="wp-label" for="correction-clock-in">{{ __('time.corrections.fields.clock_in') }}</label>
                    <input id="correction-clock-in" type="datetime-local" class="wp-input" wire:model="correctionClockIn">
                    @error('correctionClockIn') <p class="wp-field-error">{{ $message }}</p> @enderror
                </div>
                <div class="wp-field">
                    <label class="wp-label" for="correction-clock-out">{{ __('time.corrections.fields.clock_out') }}</label>
                    <input id="correction-clock-out" type="datetime-local" class="wp-input" wire:model="correctionClockOut">
                    @error('correctionClockOut') <p class="wp-field-error">{{ $message }}</p> @enderror
                </div>
                <div class="wp-field">
                    <label class="wp-label" for="correction-break-minutes">{{ __('time.corrections.fields.break_minutes') }}</label>
                    <input id="correction-break-minutes" type="number" min="0" max="1440" class="wp-input" wire:model="correctionBreakMinutes">
                    @error('correctionBreakMinutes') <p class="wp-field-error">{{ $message }}</p> @enderror
                </div>
                <div class="wp-field">
                    <label class="wp-label" for="correction-reason">{{ __('time.corrections.fields.reason') }}</label>
                    <textarea id="correction-reason" class="wp-input" rows="3" wire:model="correctionReason"></textarea>
                    @error('correctionReason') <p class="wp-field-error">{{ $message }}</p> @enderror
                </div>
                <div class="wp-cluster">
                    <button type="button" class="btn btn--surface" wire:click="closeCorrection">{{ __('common.button.cancel') }}</button>
                    <button type="submit" class="btn btn--primary">{{ __('common.button.save') }}</button>
                </div>
            </form>
        </x-wp-modal>
    @endif

    @include('partials.wp-time-force-close-modal')
</div>
