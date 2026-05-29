<div class="wp-stack">
    <div class="wp-portal-head">
        <span class="wp-brand">WinProx</span>
        <h1 class="wp-page-title">{{ __('field-portal.title') }}</h1>
        <p class="wp-muted">{{ $teamName }}</p>
    </div>

    @if ($workerId === null)
        <div class="wp-card wp-card-pad wp-stack">
            <h2 class="wp-section-title">{{ __('field-portal.identify.title') }}</h2>
            <p class="wp-muted">{{ __('field-portal.identify.hint') }}</p>

            <div class="wp-stack">
                @forelse ($workers as $worker)
                    <button type="button"
                            class="btn btn--ghost btn--block"
                            wire:key="worker-{{ $worker->id }}"
                            wire:click="selectWorker({{ $worker->id }})">
                        {{ $worker->name }}
                    </button>
                @empty
                    <p class="wp-muted">{{ __('field-portal.identify.empty') }}</p>
                @endforelse
            </div>
        </div>
    @else
        <div class="wp-card wp-card-pad wp-row">
            <span class="wp-text-body">{{ __('field-portal.tasks.signed_in_as') }} <strong>{{ collect($workers)->firstWhere('id', $workerId)?->name }}</strong></span>
            <button type="button" class="btn btn--ghost btn--sm" wire:click="signOut">{{ __('field-portal.tasks.sign_out') }}</button>
        </div>

        <h2 class="wp-section-title">{{ __('field-portal.tasks.title') }}</h2>

        <div class="wp-list">
            @forelse ($tasks as $task)
                <div class="wp-card wp-card-pad wp-stack" wire:key="task-{{ $task->id }}">
                    <div class="wp-cluster">
                        <span class="wp-pill wp-pill--{{ $task->status->pillModifier() }}">{{ __($task->status->labelKey()) }}</span>
                        @if ($task->issue?->location)
                            <span class="wp-muted">{{ $task->issue->location->name }}@if ($task->issue->unit) &middot; {{ $task->issue->unit->name }}@endif</span>
                        @endif
                    </div>

                    @if ($task->issue?->isApproved())
                        <p class="wp-text-body">{{ $task->issue->description }}</p>
                    @else
                        <div class="wp-pending-review" data-pending-label="{{ __('field-portal.pending_review') }}">
                            <p class="wp-text-body">{{ $task->issue?->description }}</p>
                        </div>
                    @endif

                    <div class="wp-stack">
                        <button type="button"
                                class="btn btn--warning btn--block"
                                wire:click="setStatus({{ $task->id }}, 'in_progress')">
                            {{ __('field-portal.tasks.set_in_progress') }}
                        </button>
                        <button type="button"
                                class="btn btn--primary btn--block"
                                wire:click="setStatus({{ $task->id }}, 'done')">
                            {{ __('field-portal.tasks.set_done') }}
                        </button>
                    </div>

                    <form wire:submit="addNote({{ $task->id }})" class="wp-stack">
                        <div class="wp-field">
                            <label class="wp-label" for="note-{{ $task->id }}">{{ __('field-portal.tasks.note') }}</label>
                            <textarea id="note-{{ $task->id }}"
                                      class="wp-textarea"
                                      rows="2"
                                      wire:model="notes.{{ $task->id }}"
                                      placeholder="{{ __('field-portal.tasks.note_placeholder') }}"></textarea>
                            @error("notes.{$task->id}") <p class="wp-error">{{ $message }}</p> @enderror
                        </div>
                        <button type="submit" class="btn btn--ghost btn--sm">{{ __('field-portal.tasks.note_submit') }}</button>
                    </form>
                </div>
            @empty
                <div class="wp-card wp-card-pad">
                    <p class="wp-muted">{{ __('field-portal.tasks.empty') }}</p>
                </div>
            @endforelse
        </div>
    @endif
</div>
