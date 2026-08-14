<x-wp-modal closeMethod="closeRoundCreateModal" aria-labelledby="issue-round-create-title" data-manual-capture="issues-round-create">
    <form wire:submit="saveRoundCreate" class="wp-card wp-card-pad wp-stack wp-modal-card wp-modal-card--wide">
        <div class="wp-modal-head">
            <div class="wp-stack-tight">
                <div class="wp-cluster">
                    <h2 id="issue-round-create-title" class="wp-section-title">{{ __('issues.round_create.title') }}</h2>
                    <x-wp-page-help page="issues.create" />
                </div>
                <p class="wp-muted wp-text-sm">{{ __('issues.round_create.subtitle') }}</p>
            </div>
            <x-wp-modal-close wire:click="closeRoundCreateModal" />
        </div>

        @if ($errors->any())
            <div class="wp-flash wp-flash--danger" role="alert">
                <ul class="wp-form-error-list">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <div class="wp-field">
            <label class="wp-label" for="round_create_description">{{ __('issues.create.description') }}</label>
            <div x-data="{ n: 0, max: {{ \App\Support\Validation\TextDescriptionLimits::MAX }} }">
                <textarea id="round_create_description" class="wp-textarea" wire:model="description" rows="3"
                          maxlength="{{ \App\Support\Validation\TextDescriptionLimits::MAX }}"
                          x-init="n = $el.value.length" x-on:input="n = $el.value.length"></textarea>
                <p class="wp-char-counter" :class="{ 'wp-char-counter--near': n >= max - 50, 'wp-char-counter--full': n >= max }"><span x-text="n"></span>/<span x-text="max"></span></p>
            </div>
            @error('description') <p class="wp-error">{{ $message }}</p> @enderror
        </div>

        <div class="wp-card wp-card-pad wp-stack-tight wp-surface-muted">
            <div class="wp-filter-bar">
                <div class="wp-field">
                    <x-wp-tooltip :text="__('issues.create.recurring_help_interval')" wrap>
                        <label class="wp-label" for="round_create_recurrence_interval_value">{{ __('issues.create.interval_value') }}</label>
                    </x-wp-tooltip>
                    <input type="number" id="round_create_recurrence_interval_value" class="wp-input" wire:model="recurrence_interval_value" min="1" max="24">
                    @error('recurrence_interval_value') <p class="wp-error">{{ $message }}</p> @enderror
                </div>
                <div class="wp-field">
                    <label class="wp-label" for="round_create_recurrence_interval_unit">{{ __('issues.create.interval_unit') }}</label>
                    <select id="round_create_recurrence_interval_unit" class="wp-select" wire:model.live="recurrence_interval_unit">
                        <option value="day">{{ __('issues.create.unit_day') }}</option>
                        <option value="week">{{ __('issues.create.unit_week') }}</option>
                        <option value="month">{{ __('issues.create.unit_month') }}</option>
                        <option value="quarter">{{ __('issues.create.unit_quarter') }}</option>
                        <option value="year">{{ __('issues.create.unit_year') }}</option>
                    </select>
                    @error('recurrence_interval_unit') <p class="wp-error">{{ $message }}</p> @enderror
                </div>
            </div>
            <div class="wp-filter-bar">
                <div class="wp-field">
                    <x-wp-tooltip :text="__('issues.create.recurring_help_lead')" wrap>
                        <label class="wp-label" for="round_create_recurrence_lead_days">{{ __('issues.create.lead_days') }}</label>
                    </x-wp-tooltip>
                    <input type="number" id="round_create_recurrence_lead_days" class="wp-input" wire:model.live="recurrence_lead_days" min="1" max="365">
                    @error('recurrence_lead_days') <p class="wp-error">{{ $message }}</p> @enderror
                </div>
                <div class="wp-field">
                    <x-wp-tooltip :text="__('issues.create.recurring_help_first_due')" wrap>
                        <label class="wp-label" for="round_create_recurrence_first_due_date">{{ __('issues.create.first_due') }}</label>
                    </x-wp-tooltip>
                    <x-wp-date-input id="round_create_recurrence_first_due_date" wire:model="recurrence_first_due_date" />
                    @error('recurrence_first_due_date') <p class="wp-error">{{ $message }}</p> @enderror
                </div>
            </div>

            <div class="wp-field">
                <x-wp-tooltip :text="__('issues.round_create.stops_help')" wrap>
                    <label class="wp-label" for="round_create_stop_unit_ids">{{ __('issues.round_create.stops') }}</label>
                </x-wp-tooltip>
                <div
                    x-data="{
                        toggleAll(event) {
                            const checked = !!event.target.checked;
                            this.$root.querySelectorAll('input[type=checkbox][data-round-stop]:not(:disabled)').forEach((box) => {
                                if (box.checked !== checked) {
                                    box.checked = checked;
                                    box.dispatchEvent(new Event('change', { bubbles: true }));
                                }
                            });
                        }
                    }"
                    class="wp-stack-tight"
                >
                    <label class="wp-check wp-text-sm">
                        <input type="checkbox" @change="toggleAll($event)" @disabled($createRoundStopUnitsGrouped->flatten(1)->isEmpty())>
                        {{ __('issues.create.round_stops_select_all') }}
                    </label>
                    <div id="round_create_stop_unit_ids" class="wp-round-stop-picker @if($createRoundStopUnitsGrouped->flatten(1)->isEmpty()) wp-round-stop-picker--disabled @endif">
                        @foreach ($createRoundStopUnitsGrouped as $locationUnits)
                            @php
                                $groupLocation = $locationUnits->first()?->location;
                                $groupLabel = $groupLocation?->name ?: ($groupLocation?->address ?? __('issues.create.location_none'));
                            @endphp
                            <div class="wp-round-stop-picker__group" role="group" aria-label="{{ $groupLabel }}">
                                <p class="wp-round-stop-picker__group-label">{{ $groupLabel }}</p>
                                @foreach ($locationUnits as $unit)
                                    @php
                                        $canUseRoundStop = $unit->allowsUnitChecks();
                                    @endphp
                                    <label class="wp-round-stop-picker__row @if(! $canUseRoundStop) wp-round-stop-picker__row--disabled @endif">
                                        <input
                                            type="checkbox"
                                            value="{{ $unit->id }}"
                                            wire:model="round_stop_unit_ids"
                                            data-round-stop
                                            @disabled(! $canUseRoundStop)
                                        >
                                        @if ($canUseRoundStop)
                                            <span>{{ $unit->localizedName() }}</span>
                                        @else
                                            <x-wp-tooltip :text="__('issues.create.round_stops_unit_checks_off')" wrap>
                                                <span>{{ $unit->localizedName() }}</span>
                                            </x-wp-tooltip>
                                        @endif
                                    </label>
                                @endforeach
                            </div>
                        @endforeach
                    </div>
                </div>
                @if ($createRoundStopUnitsGrouped->flatten(1)->isEmpty())
                    <p class="wp-muted wp-text-sm">{{ __('issues.create.round_stops_empty') }}</p>
                @else
                    <p class="wp-muted wp-text-sm">{{ __('issues.create.round_stops_select_help') }}</p>
                @endif
                @error('round_stop_unit_ids') <p class="wp-error">{{ $message }}</p> @enderror
                @error('round_stop_unit_ids.*') <p class="wp-error">{{ $message }}</p> @enderror
            </div>
        </div>

        <div class="wp-field">
            <label class="wp-label" for="round_create_internal_team_id">{{ __('issues.create.team') }}</label>
            <select id="round_create_internal_team_id" class="wp-select" wire:model="internal_team_id">
                <option value="">{{ __('issues.create.team_none') }}</option>
                @foreach ($createTeams as $team)
                    <option value="{{ $team->id }}">{{ $team->localizedName() }}</option>
                @endforeach
            </select>
            @error('internal_team_id') <p class="wp-error">{{ $message }}</p> @enderror
        </div>

        <div class="wp-field">
            <label class="wp-label" for="round_create_task_priority">{{ __('tasks.show.priority') }}</label>
            <select id="round_create_task_priority" class="wp-select" wire:model="task_priority">
                @foreach ($priorities as $priority)
                    <option value="{{ $priority->value }}">{{ $priority->label() }}</option>
                @endforeach
            </select>
            @error('task_priority') <p class="wp-error">{{ $message }}</p> @enderror
        </div>

        <div class="wp-field">
            <label class="wp-label" for="round_create_task_note">{{ __('issues.create.task_note') }}</label>
            <div x-data="{ n: 0, max: {{ \App\Support\Validation\TextDescriptionLimits::MAX }} }">
                <textarea id="round_create_task_note" class="wp-textarea" wire:model="task_note" rows="3"
                          maxlength="{{ \App\Support\Validation\TextDescriptionLimits::MAX }}"
                          x-init="n = $el.value.length" x-on:input="n = $el.value.length"></textarea>
                <p class="wp-char-counter" :class="{ 'wp-char-counter--near': n >= max - 50, 'wp-char-counter--full': n >= max }"><span x-text="n"></span>/<span x-text="max"></span></p>
            </div>
            @error('task_note') <p class="wp-error">{{ $message }}</p> @enderror
        </div>

        <div class="wp-cluster">
            <button type="button" class="btn btn--ghost" wire:click="closeRoundCreateModal">{{ __('common.button.cancel') }}</button>
            <button type="submit" class="btn btn--primary">{{ __('issues.round_create.submit') }}</button>
        </div>
    </form>
</x-wp-modal>
