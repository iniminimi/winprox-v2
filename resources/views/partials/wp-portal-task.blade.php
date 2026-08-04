{{-- Eén taakkaart met worker-acties (start/afhandelen). Melder-inhoud blurt tot goedkeuring. --}}
@php($issue = $task->issue)
@php($team = $team ?? null)
@php($worker = $worker ?? null)
@php($isRound = $issue?->isInspectionRound() ?? false)
@php($roundProgress = $isRound ? app(\App\Actions\Tasks\RoundTaskCompletionAction::class)->progress($task) : null)
@php($currentUnitId = isset($unit) ? (int) $unit->id : null)
@php($isNextStop = $isRound && $currentUnitId !== null && app(\App\Actions\Tasks\RoundTaskCompletionAction::class)->isNextOpenStop($task, $currentUnitId))
<div class="wp-card wp-card-pad wp-stack" wire:key="portal-task-{{ $task->id }}">
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

    @if ($completingTaskId === $task->id)
        {{-- Geen wire:submit naast Alpine: anders firet Livewire meteen vóór wpAwaitPhotoUploads. --}}
        <form
              x-data="{
                  isOffline: !navigator.onLine,
                  browserLocalIso() {
                      const d = new Date();
                      const pad = (n) => String(n).padStart(2, '0');
                      const tz = -d.getTimezoneOffset();
                      const sign = tz >= 0 ? '+' : '-';
                      const tzH = pad(Math.floor(Math.abs(tz) / 60));
                      const tzM = pad(Math.abs(tz) % 60);
                      return `${d.getFullYear()}-${pad(d.getMonth() + 1)}-${pad(d.getDate())}T${pad(d.getHours())}:${pad(d.getMinutes())}:${pad(d.getSeconds())}${sign}${tzH}:${tzM}`;
                  },
              }"
              x-init="
                queueMicrotask(() => window.wpRefreshAllPhotoUploadAreas?.());
                window.addEventListener('offline', () => isOffline = true);
                window.addEventListener('online', () => isOffline = false);
              "
              @submit.prevent="
                @if ($task->issue?->esg_indicator_id)
                    $wire.completingRecordedAt = browserLocalIso();
                @endif
                await window.wpAwaitPhotoUploads($el);
                $wire.submitCompleteTask();
              "
              class="wp-stack">
            @include('partials.wp-portal-esg-measurement', ['task' => $task])
            <div class="wp-field">
                <label class="wp-label" for="note-{{ $task->id }}">{{ __('portal.worker.note') }}</label>
                <textarea id="note-{{ $task->id }}" class="wp-textarea" rows="3"
                          wire:model="completingNote"
                          placeholder="{{ __('portal.worker.note_placeholder') }}"></textarea>
                @error('completingNote') <p class="wp-error">{{ $message }}</p> @enderror
            </div>
            <div class="wp-field">
                <label class="wp-label">{{ __('portal.worker.photos') }}</label>
                @include('partials.wp-issue-photo-upload', ['model' => 'completingPhotos', 'preferCamera' => true])
                @error('completingPhotos.*') <p class="wp-error">{{ $message }}</p> @enderror
            </div>
            <div class="wp-stack-tight">
                <button type="submit" class="btn btn--primary btn--block" wire:loading.attr="disabled" :disabled="isOffline">
                    <span wire:loading wire:target="submitCompleteTask" class="wp-mr-2">
                        <x-wp-spinner size="sm"/>
                    </span>
                    <span wire:loading.remove wire:target="submitCompleteTask">{{ __('portal.worker.confirm_complete') }}</span>
                    <span wire:loading wire:target="submitCompleteTask">{{ __('portal.worker.syncing') }}...</span>
                </button>
                <button type="button" class="btn btn--ghost btn--block btn--sm" wire:click="cancelCompleteTask">{{ __('common.button.cancel') }}</button>
            </div>
        </form>
    @else
        <div class="wp-stack-tight" x-data="{ isOffline: !navigator.onLine }" x-init="
            window.addEventListener('offline', () => isOffline = true);
            window.addEventListener('online', () => isOffline = false);
        ">
            @if ($task->canStart())
                <button type="button"
                        class="btn btn--warning btn--block"
                        wire:click="startTask({{ $task->id }})"
                        wire:loading.attr="disabled"
                        wire:target="startTask({{ $task->id }})"
                        :disabled="isOffline">
                    <span wire:loading wire:target="startTask({{ $task->id }})" class="wp-mr-2">
                        <x-wp-spinner size="sm"/>
                    </span>
                    <span wire:loading.remove wire:target="startTask({{ $task->id }})">{{ __('portal.worker.start_task') }}</span>
                    <span wire:loading wire:target="startTask({{ $task->id }})">{{ __('portal.worker.syncing') }}...</span>
                </button>
            @endif
            @if ($task->canComplete() && (! $isRound || ($roundProgress && $roundProgress['open'] === 0)))
                <button type="button"
                        class="btn btn--primary btn--block"
                        wire:click="beginCompleteTask({{ $task->id }})"
                        wire:loading.attr="disabled"
                        wire:target="beginCompleteTask({{ $task->id }})"
                        :disabled="isOffline">
                    <span wire:loading wire:target="beginCompleteTask({{ $task->id }})" class="wp-mr-2">
                        <x-wp-spinner size="sm"/>
                    </span>
                    <span wire:loading.remove wire:target="beginCompleteTask({{ $task->id }})">{{ __('portal.worker.complete_task') }}</span>
                    <span wire:loading wire:target="beginCompleteTask({{ $task->id }})">{{ __('portal.worker.syncing') }}...</span>
                </button>
            @endif
            @if ($isRound && $isNextStop)
                <button type="button"
                        class="btn btn--ghost btn--block btn--sm"
                        wire:click="openSkipRoundStop({{ $task->id }})"
                        :disabled="isOffline">
                    {{ __('portal.round.skip_stop') }}
                </button>
            @elseif ($isRound && $currentUnitId !== null && ($roundProgress['open'] ?? 0) > 0 && ! $isNextStop)
                <p class="wp-muted wp-text-sm">{{ __('portal.round.wait_for_next', ['name' => $roundProgress['next_unit_name'] ?? '—']) }}</p>
            @endif
        </div>
    @endif
</div>
