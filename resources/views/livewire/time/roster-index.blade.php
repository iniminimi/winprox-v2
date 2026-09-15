<div
    class="wp-stack"
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

    <div class="wp-card wp-filter-panel">
        <div class="wp-filter-form">
            <div class="wp-filter-form__row">
                <div class="wp-filter-cell">
                    <span class="wp-filter-inline-label">{{ __('time.schedule.week') }}</span>
                    <span class="wp-muted">{{ $weekLabel }}</span>
                    <div class="wp-cluster wp-cluster--tight">
                        <button type="button" class="wp-pagination__control" wire:click="previousWeek">{{ __('time.schedule.prev_week') }}</button>
                        <button type="button" class="wp-pagination__control" wire:click="thisWeek">{{ __('time.schedule.this_week') }}</button>
                        <button type="button" class="wp-pagination__control" wire:click="nextWeek">{{ __('time.schedule.next_week') }}</button>
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
            </div>
            <div class="wp-filter-form__actions">
                @can('create', \App\Models\ShiftType::class)
                    <button type="button" class="btn btn--ghost btn--sm" wire:click="openCreateType">{{ __('time.schedule.types.manage') }}</button>
                @endcan
                @can('update', \App\Models\PlannedShift::class)
                    <button type="button" class="btn btn--ghost btn--sm" data-wp-roster-save>{{ __('common.button.save') }}</button>
                    <button type="button" class="btn btn--ghost btn--sm" data-wp-roster-copy>{{ __('time.schedule.copy_next') }}</button>
                @endcan
                @can('publish', \App\Models\PlannedShift::class)
                    <button type="button" class="btn btn--primary btn--sm" data-wp-roster-publish data-confirm="{{ __('time.schedule.publish_confirm') }}">{{ __('time.schedule.publish') }}</button>
                @endcan
            </div>
        </div>
    </div>

    @if ($shiftTypes !== [])
        <div class="wp-cluster wp-cluster--wrap wp-roster-types">
            @foreach ($shiftTypes as $type)
                <span class="wp-roster-type-chip wp-roster-cell--{{ $type->color->value }} {{ $type->is_active ? '' : 'is-inactive' }}">
                    <strong>{{ $type->code }}</strong>
                    {{ $type->label }}
                    {{ $type->start_time }}–{{ $type->end_time }}
                    @can('update', $type)
                        <button type="button" class="btn btn--ghost btn--sm" wire:click="openEditType({{ $type->id }})">{{ __('common.button.edit') }}</button>
                        <button type="button" class="btn btn--ghost btn--sm" wire:click="toggleTypeActive({{ $type->id }})">
                            {{ $type->is_active ? __('time.schedule.types.deactivate') : __('time.schedule.types.activate') }}
                        </button>
                    @endcan
                </span>
            @endforeach
        </div>
    @endif

    <div class="wp-card wp-card-pad">
        @if ($snapshot->workers === [])
            <p class="wp-muted">{{ __('time.schedule.empty_workers') }}</p>
        @endif
        <p class="wp-flash wp-flash--danger" data-wp-roster-banner hidden></p>
        <div class="wp-roster-sheet" data-wp-roster-grid wire:ignore></div>
    </div>

    @if ($showTypeModal)
        <x-wp-modal closeMethod="closeTypeModal">
            <div class="wp-modal__dialog wp-card wp-card-pad">
                <div class="wp-cluster wp-cluster--spread">
                    <h2 class="wp-h2">{{ $editingTypeId ? __('time.schedule.types.edit') : __('time.schedule.types.add') }}</h2>
                    <button type="button" class="btn btn--ghost" wire:click="closeTypeModal">{{ __('common.button.cancel') }}</button>
                </div>
                <form class="wp-stack" wire:submit="saveType">
                    <div class="wp-filter-cell">
                        <label class="wp-filter-inline-label" for="type-code">{{ __('time.schedule.types.code') }}</label>
                        <input id="type-code" class="wp-input" wire:model="typeCode" maxlength="8">
                        @error('typeCode') <p class="wp-error">{{ $message }}</p> @enderror
                    </div>
                    <div class="wp-filter-cell">
                        <label class="wp-filter-inline-label" for="type-label">{{ __('time.schedule.types.label') }}</label>
                        <input id="type-label" class="wp-input" wire:model="typeLabel" maxlength="80">
                        @error('typeLabel') <p class="wp-error">{{ $message }}</p> @enderror
                    </div>
                    <div class="wp-cluster">
                        <div class="wp-filter-cell">
                            <label class="wp-filter-inline-label" for="type-start">{{ __('time.schedule.types.start') }}</label>
                            <input id="type-start" class="wp-input" wire:model="typeStart" placeholder="07:00">
                            @error('typeStart') <p class="wp-error">{{ $message }}</p> @enderror
                        </div>
                        <div class="wp-filter-cell">
                            <label class="wp-filter-inline-label" for="type-end">{{ __('time.schedule.types.end') }}</label>
                            <input id="type-end" class="wp-input" wire:model="typeEnd" placeholder="15:00">
                            @error('typeEnd') <p class="wp-error">{{ $message }}</p> @enderror
                        </div>
                        <div class="wp-filter-cell">
                            <label class="wp-filter-inline-label" for="type-break">{{ __('time.schedule.types.break') }}</label>
                            <input id="type-break" type="number" min="0" max="720" class="wp-input" wire:model="typeBreak">
                            @error('typeBreak') <p class="wp-error">{{ $message }}</p> @enderror
                        </div>
                    </div>
                    <div class="wp-filter-cell">
                        <label class="wp-filter-inline-label" for="type-color">{{ __('time.schedule.types.color') }}</label>
                        <select id="type-color" class="wp-select" wire:model="typeColor">
                            @foreach ($colors as $color)
                                <option value="{{ $color->value }}">{{ __('time.schedule.types.colors.'.$color->value) }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="wp-cluster">
                        <button type="submit" class="btn btn--primary">{{ __('common.button.save') }}</button>
                        <button type="button" class="btn btn--ghost" wire:click="closeTypeModal">{{ __('common.button.cancel') }}</button>
                    </div>
                </form>
            </div>
        </x-wp-modal>
    @endif
</div>
