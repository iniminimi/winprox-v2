<x-wp-modal closeMethod="closeCreateModal" aria-labelledby="issue-create-title" data-manual-capture="issues-create">
    @if ($createStep === 1)
        <form x-data
              x-init="queueMicrotask(() => window.wpRefreshAllPhotoUploadAreas?.())"
              x-on:submit.prevent="(async () => { await window.wpAwaitPhotoUploads?.($el); await $wire.saveCreateStepOne(); })()"
              class="wp-card wp-card-pad wp-stack wp-modal-card wp-modal-card--wide">
            <div class="wp-modal-head">
                <div class="wp-stack-tight">
                    <h2 id="issue-create-title" class="wp-section-title">{{ __('issues.create.title') }}</h2>
                    <p class="wp-muted wp-text-sm">{{ __('issues.create.step', ['step' => $createStep, 'total' => 2]) }}</p>
                </div>
                <x-wp-modal-close wire:click="closeCreateModal" />
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
                <label class="wp-label" for="create_location_id">{{ __('issues.create.location') }}</label>
                <select id="create_location_id" class="wp-select" wire:model.live="location_id">
                    <option value="">{{ __('issues.create.location_none') }}</option>
                    @foreach ($createLocations as $location)
                        <option value="{{ $location->id }}">{{ $location->name ?: $location->address }}</option>
                    @endforeach
                </select>
                @error('location_id') <p class="wp-error">{{ $message }}</p> @enderror
            </div>

            <div class="wp-field">
                <label class="wp-label" for="create_unit_id">{{ __('issues.create.unit') }}</label>
                <select id="create_unit_id" class="wp-select" wire:model="unit_id" @disabled($createUnits->isEmpty())>
                    <option value="">{{ __('issues.create.unit_none') }}</option>
                    @foreach ($createUnits as $unit)
                        <option value="{{ $unit->id }}">{{ $unit->name }}</option>
                    @endforeach
                </select>
                @error('unit_id') <p class="wp-error">{{ $message }}</p> @enderror
            </div>

            <div class="wp-field">
                <label class="wp-label" for="create_description">{{ __('issues.create.description') }}</label>
                <div x-data="{ n: 0, max: {{ \App\Support\Validation\TextDescriptionLimits::MAX }} }">
                    <textarea id="create_description" class="wp-textarea" wire:model="description" rows="4"
                              maxlength="{{ \App\Support\Validation\TextDescriptionLimits::MAX }}"
                              x-init="n = $el.value.length" x-on:input="n = $el.value.length"></textarea>
                    <p class="wp-char-counter" :class="{ 'wp-char-counter--near': n >= max - 50, 'wp-char-counter--full': n >= max }"><span x-text="n"></span>/<span x-text="max"></span></p>
                </div>
                @error('description') <p class="wp-error">{{ $message }}</p> @enderror
            </div>

            <div class="wp-field">
                <label class="wp-label">{{ __('issues.create.photos_label') }}</label>
                @include('partials.wp-issue-photo-upload', [
                    'model' => 'photos',
                    'photoAltKey' => 'issues.create.photos_add',
                ])
                @error('photos.*') <p class="wp-error">{{ $message }}</p> @enderror
                @error('photos') <p class="wp-error">{{ $message }}</p> @enderror
            </div>

            <div class="wp-field">
                <div class="wp-cluster">
                    <label class="wp-check">
                        <input type="checkbox" wire:model.live="is_recurring">
                        {{ __('issues.create.recurring') }}
                    </label>
                    <x-wp-page-help page="issues.create" />
                </div>
            </div>

            @if ($is_recurring)
                <div class="wp-card wp-card-pad wp-stack-tight wp-surface-muted">
                    <div class="wp-filter-bar">
                        <div class="wp-field">
                            <label class="wp-label" for="create_recurrence_interval_value">{{ __('issues.create.interval_value') }}</label>
                            <input type="number" id="create_recurrence_interval_value" class="wp-input" wire:model="recurrence_interval_value" min="1" max="24">
                            @error('recurrence_interval_value') <p class="wp-error">{{ $message }}</p> @enderror
                        </div>
                        <div class="wp-field">
                            <label class="wp-label" for="create_recurrence_interval_unit">{{ __('issues.create.interval_unit') }}</label>
                            <select id="create_recurrence_interval_unit" class="wp-select" wire:model.live="recurrence_interval_unit">
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
                            <label class="wp-label" for="create_recurrence_lead_days">{{ __('issues.create.lead_days') }}</label>
                            <input type="number" id="create_recurrence_lead_days" class="wp-input" wire:model.live="recurrence_lead_days" min="1" max="365">
                            @error('recurrence_lead_days') <p class="wp-error">{{ $message }}</p> @enderror
                        </div>
                        <div class="wp-field">
                            <label class="wp-label" for="create_recurrence_first_due_date">{{ __('issues.create.first_due') }}</label>
                            <x-wp-date-input id="create_recurrence_first_due_date" wire:model="recurrence_first_due_date" />
                            @error('recurrence_first_due_date') <p class="wp-error">{{ $message }}</p> @enderror
                        </div>
                    </div>

                    <div class="wp-field">
                        <label class="wp-label" for="create_round_stop_unit_ids">{{ __('issues.create.round_stops') }}</label>
                        <p class="wp-muted wp-text-sm">{{ __('issues.create.round_stops_help') }}</p>
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
                                <input type="checkbox" @change="toggleAll($event)" @disabled($createUnits->isEmpty())>
                                {{ __('issues.create.round_stops_select_all') }}
                            </label>
                        <div id="create_round_stop_unit_ids" class="wp-round-stop-picker @if($createUnits->isEmpty()) wp-round-stop-picker--disabled @endif">
                            @foreach ($createUnits as $unit)
                                @php
                                    $canUseRoundStop = $unit->allowsUnitChecks();
                                @endphp
                                @if ($canUseRoundStop)
                                    <label class="wp-round-stop-picker__row">
                                        <input
                                            type="checkbox"
                                            value="{{ $unit->id }}"
                                            wire:model="round_stop_unit_ids"
                                            data-round-stop
                                        >
                                        <span>{{ $unit->name }}</span>
                                    </label>
                                @else
                                    <x-wp-tooltip :text="__('issues.create.round_stops_unit_checks_off')" wrap class="wp-tooltip--block">
                                        <label class="wp-round-stop-picker__row wp-round-stop-picker__row--disabled">
                                            <input type="checkbox" value="{{ $unit->id }}" disabled>
                                            <span>{{ $unit->name }}</span>
                                        </label>
                                    </x-wp-tooltip>
                                @endif
                            @endforeach
                        </div>
                        </div>
                        @if ($createUnits->isEmpty())
                            <p class="wp-muted wp-text-sm">{{ __('issues.create.round_stops_empty') }}</p>
                        @else
                            <p class="wp-muted wp-text-sm">{{ __('issues.create.round_stops_select_help') }}</p>
                        @endif
                        @error('round_stop_unit_ids') <p class="wp-error">{{ $message }}</p> @enderror
                        @error('round_stop_unit_ids.*') <p class="wp-error">{{ $message }}</p> @enderror
                    </div>

                    @if ($hasEsgModule)
                        <div class="wp-field">
                            <label class="wp-label" for="create_esg_indicator_id">{{ __('issues.create.esg_indicator') }}</label>
                            <select id="create_esg_indicator_id" class="wp-select" wire:model="esg_indicator_id">
                                <option value="">{{ __('issues.create.esg_indicator_none') }}</option>
                                @foreach ($createEsgIndicators as $indicator)
                                    <option value="{{ $indicator->id }}">
                                        {{ $indicator->localizedName() }}
                                        @if ($indicator->unit_of_measure)
                                            ({{ $indicator->unit_of_measure }})
                                        @endif
                                    </option>
                                @endforeach
                            </select>
                            <p class="wp-muted wp-text-sm">{{ __('issues.create.esg_indicator_hint') }}</p>
                            @error('esg_indicator_id') <p class="wp-error">{{ $message }}</p> @enderror
                        </div>
                    @endif
                </div>
            @endif

            <div class="wp-cluster">
                <button type="submit" class="btn btn--primary">{{ __('issues.create.next') }}</button>
            </div>
        </form>
    @else
        <form wire:submit="saveCreateStepTwo" class="wp-card wp-card-pad wp-stack wp-modal-card">
            <div class="wp-modal-head">
                <div class="wp-stack-tight">
                    <h2 id="issue-create-title" class="wp-section-title">{{ __('issues.create.title') }}</h2>
                    <p class="wp-muted wp-text-sm">{{ __('issues.create.step', ['step' => $createStep, 'total' => 2]) }}</p>
                </div>
                <x-wp-modal-close wire:click="closeCreateModal" />
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

            <p class="wp-muted">{{ __('issues.create.step_two_hint') }}</p>

            <div class="wp-field">
                <label class="wp-label" for="create_internal_team_id">{{ __('issues.create.team') }}</label>
                <select id="create_internal_team_id" class="wp-select" wire:model="internal_team_id">
                    <option value="">{{ __('issues.create.team_none') }}</option>
                    @foreach ($createTeams as $team)
                        <option value="{{ $team->id }}">{{ $team->localizedName() }}</option>
                    @endforeach
                </select>
                @error('internal_team_id') <p class="wp-error">{{ $message }}</p> @enderror
            </div>

            <div class="wp-field">
                <label class="wp-label" for="create_task_priority">{{ __('tasks.show.priority') }}</label>
                <select id="create_task_priority" class="wp-select" wire:model="task_priority">
                    @foreach ($priorities as $priority)
                        <option value="{{ $priority->value }}">{{ $priority->label() }}</option>
                    @endforeach
                </select>
                @error('task_priority') <p class="wp-error">{{ $message }}</p> @enderror
            </div>

            <div class="wp-field">
                <label class="wp-label" for="create_task_note">{{ __('issues.create.task_note') }}</label>
                <div x-data="{ n: 0, max: {{ \App\Support\Validation\TextDescriptionLimits::MAX }} }">
                    <textarea id="create_task_note" class="wp-textarea" wire:model="task_note" rows="3"
                              maxlength="{{ \App\Support\Validation\TextDescriptionLimits::MAX }}"
                              x-init="n = $el.value.length" x-on:input="n = $el.value.length"></textarea>
                    <p class="wp-char-counter" :class="{ 'wp-char-counter--near': n >= max - 50, 'wp-char-counter--full': n >= max }"><span x-text="n"></span>/<span x-text="max"></span></p>
                </div>
                @error('task_note') <p class="wp-error">{{ $message }}</p> @enderror
            </div>

            <div class="wp-cluster">
                <button type="button" class="btn btn--ghost" wire:click="backCreateToStepOne">{{ __('issues.create.back') }}</button>
                <button type="submit" class="btn btn--primary">{{ __('issues.create.finish') }}</button>
            </div>
        </form>
    @endif
</x-wp-modal>
