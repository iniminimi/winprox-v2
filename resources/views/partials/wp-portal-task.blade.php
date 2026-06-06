{{-- Eén taakkaart met worker-acties (start/afhandelen). Melder-inhoud blurt tot goedkeuring. --}}
@php($issue = $task->issue)
@php($team = $team ?? null)
@php($worker = $worker ?? null)
<div class="wp-card wp-card-pad wp-stack" wire:key="portal-task-{{ $task->id }}">
    @include('partials.wp-portal-task-lines', ['task' => $task, 'issue' => $issue])

    @include('partials.wp-portal-issue-photos', [
        'issue' => $issue,
        'wireKeyPrefix' => 'tp-'.$task->id,
    ])

    @if ($issue && $issue->updates && $issue->updates->isNotEmpty())
        @foreach ($issue->updates as $update)
            <p class="wp-text-sm wp-muted">Update : {{ $update->body }}</p>
        @endforeach
    @endif

    @if ($completingTaskId === $task->id)
        <form wire:submit="submitCompleteTask"
              x-data="{ isOffline: !navigator.onLine }"
              x-init="
                queueMicrotask(() => window.wpRefreshAllPhotoUploadAreas?.());
                window.addEventListener('offline', () => isOffline = true);
                window.addEventListener('online', () => isOffline = false);
            "
              @submit.prevent="await window.wpAwaitPhotoUploads($el); $wire.submitCompleteTask()"
              class="wp-stack">
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
            @if ($task->canComplete())
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
        </div>
    @endif
</div>
