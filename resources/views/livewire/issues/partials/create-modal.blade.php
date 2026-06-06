<x-wp-modal closeMethod="closeCreateModal" aria-labelledby="issue-create-title">
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
                <textarea id="create_description" class="wp-textarea" wire:model="description" rows="4"></textarea>
                @error('description') <p class="wp-error">{{ $message }}</p> @enderror
            </div>

            <div class="wp-field">
                <label class="wp-label">{{ __('issues.create.photos_label') }}</label>
                @include('partials.wp-issue-photo-upload', [
                    'model' => 'photos',
                    'photoAlt' => __('issues.create.photos_add'),
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
                            <input type="date" id="create_recurrence_first_due_date" class="wp-input" wire:model="recurrence_first_due_date">
                            @error('recurrence_first_due_date') <p class="wp-error">{{ $message }}</p> @enderror
                        </div>
                    </div>
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
                        <option value="{{ $team->id }}">{{ $team->name }}</option>
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
                <textarea id="create_task_note" class="wp-textarea" wire:model="task_note" rows="3"></textarea>
                @error('task_note') <p class="wp-error">{{ $message }}</p> @enderror
            </div>

            <div class="wp-cluster">
                <button type="button" class="btn btn--ghost" wire:click="backCreateToStepOne">{{ __('issues.create.back') }}</button>
                <button type="submit" class="btn btn--primary">{{ __('issues.create.finish') }}</button>
            </div>
        </form>
    @endif
</x-wp-modal>
