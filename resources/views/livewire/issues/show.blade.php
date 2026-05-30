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

        @if ($issue->is_recurring)
            <div class="wp-card wp-card-pad wp-surface-2 wp-stack-tight">
                <div class="wp-row">
                    <div class="wp-grow wp-stack-tight">
                        <p class="wp-label">{{ __('issues.show.recurring_title') }}</p>
                        @if ($issue->recurrence_next_due_at)
                            <p class="wp-muted">{{ __('issues.show.recurring_next_due', ['date' => $issue->recurrence_next_due_at->format('d-m-Y')]) }}</p>
                        @endif
                        @if ($issue->recurrence_paused_at)
                            <span class="wp-pill wp-pill--closed">{{ __('issues.show.recurring_paused') }}</span>
                        @else
                            <span class="wp-pill wp-pill--progress">{{ __('issues.show.recurring_active') }}</span>
                        @endif
                    </div>
                    <button type="button" class="btn btn--ghost btn--sm" wire:click="toggleRecurrencePause">
                        {{ $issue->recurrence_paused_at ? __('issues.show.recurring_resume') : __('issues.show.recurring_pause') }}
                    </button>
                </div>
            </div>
        @endif

        <p class="wp-text-body">{{ $issue->description }}</p>

        @php
            $reportPhotos = $issue->photos->whereNull('issue_update_id');
        @endphp
        @if ($reportPhotos->isNotEmpty())
            <div class="wp-photo-grid">
                @foreach ($reportPhotos as $photo)
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
                        <a href="{{ route('tasks.show', $task) }}" class="wp-text-body">{{ $task->team?->name ?? __('issues.show.no_team') }}</a>
                    </div>
                    <a href="{{ route('tasks.show', $task) }}" class="btn btn--ghost btn--sm">{{ __('issues.show.manage_task_status') }}</a>
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

    <div class="wp-card wp-card-pad wp-stack">
        <h2 class="wp-section-title">{{ __('issues.show.updates') }}</h2>

        @forelse ($issue->updates as $update)
            <div class="wp-stack-tight wp-border-top" wire:key="update-{{ $update->id }}">
                <p class="wp-muted">
                    {{ $update->created_at?->format('d/m/Y H:i') }}
                    @if ($update->user)
                        — {{ $update->user->name }}
                    @elseif ($update->worker)
                        — {{ $update->worker->displayName() }}
                    @endif
                    @if ($update->kind && $update->kind !== 'note')
                        · {{ __('issues.updates.kind.'.$update->kind) }}
                    @endif
                </p>
                @if ($update->body)
                    <p class="wp-text-body">{{ $update->body }}</p>
                @endif
                @if ($update->photos->isNotEmpty())
                    <div class="wp-photo-grid">
                        @foreach ($update->photos as $photo)
                            <div class="wp-photo-thumb" wire:key="up-{{ $update->id }}-{{ $photo->id }}">
                                <img src="{{ \Illuminate\Support\Facades\Storage::disk('public')->url($photo->path) }}" alt="">
                            </div>
                        @endforeach
                    </div>
                @endif
            </div>
        @empty
            <p class="wp-muted">{{ __('issues.show.updates_empty') }}</p>
        @endforelse

        <form x-data
              x-init="queueMicrotask(() => window.wpRefreshAllPhotoUploadAreas?.())"
              @submit.prevent="await window.wpAwaitPhotoUploads($el); $wire.saveUpdate()"
              class="wp-stack wp-border-top">
            <div class="wp-field">
                <label class="wp-label" for="updateBody">{{ __('issues.show.add_update') }}</label>
                <textarea id="updateBody" class="wp-textarea" wire:model="updateBody" rows="3"
                          placeholder="{{ __('issues.show.add_update_placeholder') }}"></textarea>
                @error('updateBody') <p class="wp-error">{{ $message }}</p> @enderror
            </div>
            <div class="wp-field">
                <label class="wp-label">{{ __('issues.show.add_update_photos') }}</label>
                @include('partials.wp-issue-photo-upload', [
                    'model' => 'updatePhotos',
                    'removeMethod' => 'removeUpdatePhoto',
                ])
                @error('updatePhotos.*') <p class="wp-error">{{ $message }}</p> @enderror
            </div>
            <button type="submit" class="btn btn--primary btn--sm" wire:loading.attr="disabled">
                {{ __('issues.show.add_update_submit') }}
            </button>
        </form>
    </div>
</div>
