<div class="wp-stack">
    <div class="wp-row">
        <div class="wp-stack-tight">
            <a href="{{ route('tasks.index') }}" class="btn btn--ghost btn--sm">{{ __('tasks.show.back') }}</a>
            <div class="wp-cluster">
                <h1 class="wp-page-title">{{ __('tasks.show.title') }}</h1>
                <x-wp-ref-nr type="task" :id="$task->id" />
            </div>
        </div>
        <span class="wp-pill wp-pill--{{ $task->status->pillModifier() }}">{{ __($task->status->labelKey()) }}</span>
    </div>

    <div class="wp-card wp-card-pad wp-stack">
        <div class="wp-cluster">
            <h2 class="wp-section-title">{{ __('tasks.show.issue_context') }}</h2>
            @if ($task->issue)
                <x-wp-ref-nr :id="$task->issue->id" />
            @endif
        </div>
        <p class="wp-melding-desc">{{ $task->issue?->description }}</p>
        @php
            $locationLine = collect([
                $task->issue?->location?->name,
                $task->issue?->unit?->name,
                $task->issue?->location?->address,
            ])->filter()->join(' · ');
        @endphp
        @if ($locationLine !== '')
            <p class="wp-muted">{{ $locationLine }}</p>
        @endif
        <a href="{{ route('issues.show', $task->issue) }}" class="btn btn--ghost btn--sm">{{ __('tasks.show.view_issue') }}</a>
    </div>

    <div class="wp-card wp-card-pad wp-stack">
        <h2 class="wp-section-title">{{ __('tasks.show.task') }}</h2>
        <p class="wp-muted">{{ $task->team?->name ?: __('tasks.card.no_team') }}</p>
        @if ($task->note)
            <p>{{ $task->note }}</p>
        @endif
        @if ($task->scheduled_for || $task->due_at)
            <p class="wp-muted">{{ __('tasks.show.due', ['date' => ($task->scheduled_for ?? $task->due_at)?->format('d/m/Y')]) }}</p>
        @endif
    </div>

    @if ($transitions !== [])
        <div class="wp-card wp-card-pad wp-stack">
            <h2 class="wp-section-title">{{ __('tasks.show.change_status') }}</h2>
            <div class="wp-chip-row">
                @foreach ($transitions as $status)
                    <button type="button"
                            class="btn btn--ghost btn--sm {{ $targetStatus === $status->value ? 'is-active' : '' }}"
                            wire:click="selectStatus('{{ $status->value }}')">
                        {{ __($status->labelKey()) }}
                    </button>
                @endforeach
            </div>

            @if ($targetStatus !== '')
                @if ($requiresReason)
                    <div class="wp-field">
                        <label class="wp-label" for="reason">{{ __('tasks.show.reason') }}</label>
                        <textarea id="reason" class="wp-textarea" wire:model="reason" rows="3"></textarea>
                        @error('reason') <p class="wp-error">{{ $message }}</p> @enderror
                    </div>
                @endif
                <button type="button" class="btn btn--primary" wire:click="updateStatus">{{ __('tasks.show.confirm_status') }}</button>
            @endif

            @if ($task->status === \App\Enums\TaskStatus::InProgress)
                <div class="wp-field wp-stack-tight wp-border-top">
                    <label class="wp-label" for="pauseNote">{{ __('tasks.show.pause') }}</label>
                    <textarea id="pauseNote" class="wp-textarea" wire:model="pauseNote" rows="2"></textarea>
                    @error('pauseNote') <p class="wp-error">{{ $message }}</p> @enderror
                    <button type="button" class="btn btn--ghost" wire:click="pause">{{ __('tasks.show.pause_submit') }}</button>
                </div>
            @endif
        </div>
    @endif

    @if ($task->issue?->updates->isNotEmpty())
        <div class="wp-card wp-card-pad wp-stack">
            <h2 class="wp-section-title">{{ __('tasks.show.updates') }}</h2>
            @foreach ($task->issue->updates->sortByDesc('created_at') as $update)
                <div class="wp-stack-tight" wire:key="update-{{ $update->id }}">
                    <p class="wp-muted">{{ optional($update->created_at)->format('d/m/Y H:i') }}
                        @if ($update->user) — {{ $update->user->name }} @endif
                    </p>
                    <p>{{ $update->body }}</p>
                </div>
            @endforeach
        </div>
    @endif
</div>
