<div class="wp-stack">
    <div class="wp-row">
        <div class="wp-cluster">
            <h1 class="wp-page-title">{{ __('issues.show.title') }}</h1>
            <span class="wp-pill wp-pill--{{ $issue->status->pillModifier() }}">{{ __($issue->status->labelKey()) }}</span>
        </div>
        <a href="{{ route('issues.index') }}" class="btn btn--ghost btn--sm">{{ __('issues.show.back') }}</a>
    </div>

    <div class="wp-card wp-card-pad wp-stack">
        <div class="wp-row">
            <h2 class="wp-section-title">{{ __('issues.show.report') }}</h2>
            @unless ($issue->isApproved())
                <div class="wp-cluster">
                    <span class="wp-pill wp-pill--progress">{{ __('issues.pending_review') }}</span>
                    <button type="button" class="btn btn--warning btn--sm" wire:click="approve">{{ __('issues.approve') }}</button>
                </div>
            @endunless
        </div>

        @if ($issue->location)
            <p class="wp-muted">{{ $issue->location->name }}@if ($issue->unit) &middot; {{ $issue->unit->name }}@endif</p>
        @endif

        @if ($issue->reporter_name || $issue->reporter_contact)
            <p class="wp-muted">{{ $issue->reporter_name }}@if ($issue->reporter_contact) ({{ $issue->reporter_contact }})@endif</p>
        @endif

        <p class="wp-text-body">{{ $issue->description }}</p>

        @if ($issue->photos->isNotEmpty())
            <div class="wp-photo-grid">
                @foreach ($issue->photos as $photo)
                    <div class="wp-photo-thumb" wire:key="photo-{{ $photo->id }}">
                        <img src="{{ \Illuminate\Support\Facades\Storage::disk('public')->url($photo->path) }}" alt="">
                    </div>
                @endforeach
            </div>
        @endif
    </div>

    <div class="wp-card wp-card-pad wp-stack">
        <h2 class="wp-section-title">{{ __('issues.show.tasks') }}</h2>

        <div class="wp-list">
            @forelse ($issue->tasks as $task)
                <div class="wp-row" wire:key="task-{{ $task->id }}">
                    <div class="wp-cluster">
                        <span class="wp-pill wp-pill--{{ $task->status->pillModifier() }}">{{ __($task->status->labelKey()) }}</span>
                        <span class="wp-text-body">{{ $task->team?->name ?? __('issues.show.no_team') }}</span>
                    </div>
                    <select class="wp-select wp-select--inline"
                            wire:change="changeTaskStatus({{ $task->id }}, $event.target.value)">
                        @foreach ($statuses as $status)
                            <option value="{{ $status->value }}" @selected($task->status === $status)>{{ __($status->labelKey()) }}</option>
                        @endforeach
                    </select>
                </div>
            @empty
                <p class="wp-muted">{{ __('issues.show.tasks_empty') }}</p>
            @endforelse
        </div>

        <form wire:submit="addTask" class="wp-stack">
            <div class="wp-field">
                <label class="wp-label" for="newTeamId">{{ __('issues.show.add_task') }}</label>
                <div class="wp-cluster">
                    <select id="newTeamId" class="wp-select wp-grow" wire:model="newTeamId">
                        <option value="">{{ __('issues.show.add_task_placeholder') }}</option>
                        @foreach ($teams as $team)
                            <option value="{{ $team->id }}">{{ $team->name }}</option>
                        @endforeach
                    </select>
                    <button type="submit" class="btn btn--primary btn--sm">{{ __('issues.show.add_task_submit') }}</button>
                </div>
                @error('newTeamId') <p class="wp-error">{{ $message }}</p> @enderror
            </div>
        </form>
    </div>
</div>
