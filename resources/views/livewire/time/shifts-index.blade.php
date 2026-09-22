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
    @if (session('time_flash_error'))
        <div class="wp-flash wp-flash--danger">{{ session('time_flash_error') }}</div>
    @endif

    <div class="wp-card wp-filter-panel wp-time-shifts-toolbar">
        <div class="wp-filter-form wp-time-shifts-toolbar__form">
            <div class="wp-filter-form__row wp-time-shifts-toolbar__primary">
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
                            <option value="{{ $team->id }}">{{ $team->localizedName() }}</option>
                        @endforeach
                    </select>
                </div>
            </div>
            <div class="wp-filter-form__row">
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
                @can('manualClockIn', \App\Models\WorkShift::class)
                    <button type="button" class="btn btn--ghost btn--sm" wire:click="openManualClockIn">
                        {{ __('time.manual_clock_in.button') }}
                    </button>
                @endcan
                <button type="button" class="btn btn--primary btn--sm" wire:click="applyFilters">{{ __('time.filters.apply') }}</button>
                <x-wp-list-export :csv-url="$exportUrl" :print-url="$printUrl" />
            </div>
        </div>
    </div>

    <div class="wp-list">
        @forelse ($shifts as $shift)
            @include('partials.wp-time-shift-card', ['shift' => $shift])
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
                    <p class="wp-muted wp-text-sm">{{ __('time.corrections.fields.clock_out_hint') }}</p>
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
    @include('partials.wp-time-manual-clock-in-modal')
</div>
