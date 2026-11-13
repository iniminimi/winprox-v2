<div class="wp-field">
    <span class="wp-label">{{ __('team.library.audience') }}</span>
    <label class="wp-check">
        <input type="radio" wire:model.live="audience" value="team">
        <span>{{ __('team.library.audience_team') }}</span>
    </label>
    <label class="wp-check">
        <input type="radio" wire:model.live="audience" value="worker">
        <span>{{ __('team.library.audience_worker') }}</span>
    </label>
    @error('audience') <span class="wp-error">{{ $message }}</span> @enderror
</div>

@if ($audience === 'worker')
    <div class="wp-field">
        <label class="wp-label" for="team-library-worker-{{ $formId }}">{{ __('team.library.worker') }}</label>
        <select id="team-library-worker-{{ $formId }}" class="wp-select" wire:model="workerId">
            <option value="">{{ __('team.library.worker_placeholder') }}</option>
            @foreach ($workers as $worker)
                <option value="{{ $worker->id }}">{{ $worker->displayName() }}</option>
            @endforeach
        </select>
        @error('workerId') <span class="wp-error">{{ $message }}</span> @enderror
    </div>
@endif
