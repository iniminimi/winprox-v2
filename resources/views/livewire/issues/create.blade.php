<div class="wp-stack">
    <div class="wp-row">
        <h1 class="wp-page-title">{{ __('issues.create.title') }}</h1>
        <a href="{{ route('issues.index') }}" class="btn btn--ghost btn--sm">{{ __('common.button.cancel') }}</a>
    </div>

    <form wire:submit="save" class="wp-card wp-card-pad wp-stack">
        <div class="wp-field">
            <label class="wp-label" for="location_id">{{ __('issues.create.location') }}</label>
            <select id="location_id" class="wp-select" wire:model.live="location_id">
                <option value="">{{ __('issues.create.location_none') }}</option>
                @foreach ($locations as $location)
                    <option value="{{ $location->id }}">{{ $location->name }}</option>
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
            <label class="wp-label" for="reporter_name">{{ __('issues.create.reporter_name') }}</label>
            <input type="text" id="reporter_name" class="wp-input" wire:model="reporter_name">
            @error('reporter_name') <p class="wp-error">{{ $message }}</p> @enderror
        </div>

        <div class="wp-field">
            <label class="wp-label" for="reporter_contact">{{ __('issues.create.reporter_contact') }}</label>
            <input type="text" id="reporter_contact" class="wp-input" wire:model="reporter_contact">
            @error('reporter_contact') <p class="wp-error">{{ $message }}</p> @enderror
        </div>

        <div class="wp-field">
            <label class="wp-label" for="description">{{ __('issues.create.description') }}</label>
            <textarea id="description" class="wp-textarea" wire:model="description"></textarea>
            @error('description') <p class="wp-error">{{ $message }}</p> @enderror
        </div>

        <div class="wp-field">
            <span class="wp-label">{{ __('issues.create.teams') }}</span>
            <p class="wp-hint">{{ __('issues.create.teams_hint') }}</p>
            @forelse ($teams as $team)
                <label class="wp-check" wire:key="team-{{ $team->id }}">
                    <input type="checkbox" value="{{ $team->id }}" wire:model="team_ids">
                    {{ $team->name }}
                </label>
            @empty
                <p class="wp-muted">{{ __('issues.create.teams_empty') }}</p>
            @endforelse
            @error('team_ids') <p class="wp-error">{{ $message }}</p> @enderror
        </div>

        <div class="wp-cluster">
            <button type="submit" class="btn btn--primary">{{ __('issues.create.submit') }}</button>
        </div>
    </form>
</div>
