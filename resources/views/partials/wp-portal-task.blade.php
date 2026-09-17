{{-- Eén taakkaart met worker-acties (start/afhandelen). Melder-inhoud blurt tot goedkeuring. --}}
@php($issue = $task->issue)
@php($team = $team ?? null)
@php($worker = $worker ?? null)
@php($isRound = $issue?->isInspectionRound() ?? false)
@php($roundProgress = $isRound ? app(\App\Actions\Tasks\RoundTaskCompletionAction::class)->progress($task) : null)
@php($currentUnitId = isset($unit) ? (int) $unit->id : null)
@php($isNextStop = $isRound && $currentUnitId !== null && app(\App\Actions\Tasks\RoundTaskCompletionAction::class)->isNextOpenStop($task, $currentUnitId))
<div
    class="wp-card wp-card-pad wp-stack"
    wire:key="portal-task-{{ $task->id }}"
    data-wp-task="{{ $task->id }}"
    @if ($task->canComplete()) data-wp-can-complete="1" @endif
    x-data="{ completing: @js($completingTaskId === $task->id) }"
>
    @include('partials.wp-portal-task-lines', ['task' => $task, 'issue' => $issue])

    @if ($isRound && $roundProgress)
        @include('partials.wp-portal-round-progress', [
            'progress' => $roundProgress,
            'currentUnitId' => $currentUnitId,
        ])
    @endif

    @include('partials.wp-portal-issue-photos', [
        'issue' => $issue,
        'wireKeyPrefix' => 'tp-'.$task->id,
    ])

    @if ($issue && $issue->updates && $issue->updates->isNotEmpty())
        @foreach ($issue->updates as $update)
            <p class="wp-text-sm wp-muted">Update : {{ $update->description }}</p>
        @endforeach
    @endif

    <p class="wp-pill wp-pill--done" data-wp-task-local-done>{{ __('portal.worker.task_completed') }}</p>

    <form
          x-show="completing"
          x-cloak
          data-wp-complete-form
          x-init="queueMicrotask(() => window.wpRefreshAllPhotoUploadAreas?.())"
          @submit.prevent="
            try {
                await window.wpFieldCompleteTask($el, {{ $task->id }});
                completing = false;
            } catch (error) {
                window.dispatchEvent(new CustomEvent('wp-field-ui', {
                    detail: { message: error.message || @js(__('portal.field_sync.error')) },
                }));
            }
          "
          class="wp-stack">
        @include('partials.wp-portal-esg-measurement', ['task' => $task])
        <div class="wp-field">
            <label class="wp-label" for="note-{{ $task->id }}">{{ __('portal.worker.note') }}</label>
            <textarea id="note-{{ $task->id }}" class="wp-textarea" rows="3"
                      data-wp-complete-note
                      wire:model="completingNote"
                      placeholder="{{ __('portal.worker.note_placeholder') }}"></textarea>
            @error('completingNote') <p class="wp-error">{{ $message }}</p> @enderror
        </div>
        <div class="wp-field">
            <label class="wp-label">{{ __('portal.worker.photos') }}</label>
            @include('partials.wp-issue-photo-upload', ['model' => 'completingPhotos', 'preferCamera' => true, 'storeLocal' => true])
            @error('completingPhotos.*') <p class="wp-error">{{ $message }}</p> @enderror
        </div>
        <div class="wp-stack-tight">
            <button type="submit" class="btn btn--primary btn--block">
                {{ __('portal.worker.confirm_complete') }}
            </button>
            <button type="button" class="btn btn--ghost btn--block btn--sm" @click="completing = false">{{ __('common.button.cancel') }}</button>
        </div>
    </form>

    <div class="wp-stack-tight" data-wp-task-actions x-show="!completing">
        @if ($task->canStart())
            <button type="button"
                    class="btn btn--warning btn--block"
                    data-wp-task-start
                    @click="window.wpFieldStartTask({{ $task->id }})">
                {{ __('portal.worker.start_task') }}
            </button>
        @endif
        @if ($task->canComplete() && (! $isRound || ($roundProgress && $roundProgress['open'] === 0)))
            <button type="button"
                    class="btn btn--primary btn--block"
                    data-wp-task-complete
                    @click="completing = true">
                {{ __('portal.worker.complete_task') }}
            </button>
        @elseif ($task->canStart() && (! $isRound || ($roundProgress && $roundProgress['open'] === 0)))
            <button type="button"
                    class="btn btn--primary btn--block"
                    data-wp-task-complete
                    @click="completing = true">
                {{ __('portal.worker.complete_task') }}
            </button>
        @endif
        @if ($isRound && $isNextStop)
            <p class="wp-muted wp-text-sm">{{ __('portal.round.do_check_here') }}</p>
            <button type="button"
                    class="btn btn--ghost btn--block btn--sm"
                    wire:click="openSkipRoundStop({{ $task->id }})"
                    x-data="{ isOffline: !navigator.onLine }"
                    x-init="
                        window.addEventListener('offline', () => isOffline = true);
                        window.addEventListener('online', () => isOffline = false);
                    "
                    :disabled="isOffline">
                {{ __('portal.round.skip_stop') }}
            </button>
        @elseif ($isRound && $currentUnitId !== null && ($roundProgress['open'] ?? 0) > 0 && ! $isNextStop)
            <p class="wp-muted wp-text-sm">{{ __('portal.round.wait_for_next', ['name' => $roundProgress['next_unit_name'] ?? '—']) }}</p>
        @endif
    </div>
</div>
