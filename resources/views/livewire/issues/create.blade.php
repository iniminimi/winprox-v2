<div class="wp-stack">
    <div class="wp-row">
        <div class="wp-stack-tight">
            <h1 class="wp-page-title">{{ __('issues.create.title') }}</h1>
            <p class="wp-muted">{{ __('issues.create.step', ['step' => $step, 'total' => 2]) }}</p>
        </div>
        <a href="{{ route('issues.index') }}" class="btn btn--ghost btn--sm">{{ __('common.button.cancel') }}</a>
    </div>

    @if ($step === 1)
        <form wire:submit="saveStepOne" class="wp-card wp-card-pad wp-stack">
            <div class="wp-field">
                <label class="wp-label" for="location_id">{{ __('issues.create.location') }}</label>
                <select id="location_id" class="wp-select" wire:model.live="location_id">
                    <option value="">{{ __('issues.create.location_none') }}</option>
                    @foreach ($locations as $location)
                        <option value="{{ $location->id }}">{{ $location->name ?: $location->address }}</option>
                    @endforeach
                </select>
                @error('location_id') <p class="wp-error">{{ $message }}</p> @enderror
            </div>

            <div class="wp-field">
                <label class="wp-label" for="unit_id">{{ __('issues.create.unit') }}</label>
                <select id="unit_id" class="wp-select" wire:model="unit_id" @disabled($units->isEmpty())>
                    <option value="">{{ __('issues.create.unit_none') }}</option>
                    @foreach ($units as $unit)
                        <option value="{{ $unit->id }}">{{ $unit->name }}</option>
                    @endforeach
                </select>
                @error('unit_id') <p class="wp-error">{{ $message }}</p> @enderror
            </div>

            <div class="wp-field">
                <label class="wp-label" for="description">{{ __('issues.create.description') }}</label>
                <textarea id="description" class="wp-textarea" wire:model="description" rows="4"></textarea>
                @error('description') <p class="wp-error">{{ $message }}</p> @enderror
            </div>

            <div class="wp-field">
                <label class="wp-check">
                    <input type="checkbox" wire:model.live="is_recurring">
                    {{ __('issues.create.recurring') }}
                </label>
            </div>

            @if ($is_recurring)
                <div class="wp-card wp-card-pad wp-stack-tight wp-surface-muted">
                    <div class="wp-filter-bar">
                        <div class="wp-field">
                            <label class="wp-label" for="recurrence_interval_value">{{ __('issues.create.interval_value') }}</label>
                            <input type="number" id="recurrence_interval_value" class="wp-input" wire:model="recurrence_interval_value" min="1" max="24">
                            @error('recurrence_interval_value') <p class="wp-error">{{ $message }}</p> @enderror
                        </div>
                        <div class="wp-field">
                            <label class="wp-label" for="recurrence_interval_unit">{{ __('issues.create.interval_unit') }}</label>
                            <select id="recurrence_interval_unit" class="wp-select" wire:model="recurrence_interval_unit">
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
                            <label class="wp-label" for="recurrence_lead_days">{{ __('issues.create.lead_days') }}</label>
                            <input type="number" id="recurrence_lead_days" class="wp-input" wire:model="recurrence_lead_days" min="1" max="365">
                            @error('recurrence_lead_days') <p class="wp-error">{{ $message }}</p> @enderror
                        </div>
                        <div class="wp-field">
                            <label class="wp-label" for="recurrence_first_due_date">{{ __('issues.create.first_due') }}</label>
                            <input type="date" id="recurrence_first_due_date" class="wp-input" wire:model="recurrence_first_due_date">
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
        <form wire:submit="saveStepTwo" class="wp-card wp-card-pad wp-stack">
            <p class="wp-muted">{{ __('issues.create.step_two_hint') }}</p>

            <div class="wp-field">
                <label class="wp-label" for="internal_team_id">{{ __('issues.create.team') }}</label>
                <select id="internal_team_id" class="wp-select" wire:model="internal_team_id" required>
                    <option value="">{{ __('issues.create.team_none') }}</option>
                    @foreach ($teams as $team)
                        <option value="{{ $team->id }}">{{ $team->name }}</option>
                    @endforeach
                </select>
                @error('internal_team_id') <p class="wp-error">{{ $message }}</p> @enderror
            </div>

            <div class="wp-field">
                <label class="wp-label" for="task_note">{{ __('issues.create.task_note') }}</label>
                <textarea id="task_note" class="wp-textarea" wire:model="task_note" rows="3"></textarea>
                @error('task_note') <p class="wp-error">{{ $message }}</p> @enderror
            </div>

            <div class="wp-cluster">
                <button type="button" class="btn btn--ghost" wire:click="backToStepOne">{{ __('issues.create.back') }}</button>
                <button type="submit" class="btn btn--primary">{{ __('issues.create.finish') }}</button>
            </div>
        </form>
    @endif
</div>
