{{-- Eén taakkaart met worker-acties (start/afhandelen). Melder-inhoud blurt tot goedkeuring. --}}
@php($issue = $task->issue)
<div class="wp-card wp-card-pad wp-stack" wire:key="portal-task-{{ $task->id }}">
    <div class="wp-cluster">
        <span class="wp-pill wp-pill--{{ $task->status->pillModifier() }}">{{ __($task->status->labelKey()) }}</span>
    </div>

    @if ($issue?->isApproved())
        <p class="wp-text-body">{{ $issue->description }}</p>
    @else
        <div class="wp-pending-review" data-pending-label="{{ __('portal.pending_review') }}">
            <p class="wp-text-body">{{ $issue?->description }}</p>
        </div>
    @endif

    @if ($issue && $issue->photos->isNotEmpty())
        @if ($issue->isApproved())
            <div class="wp-photo-grid">
                @foreach ($issue->photos as $photo)
                    <div class="wp-photo-thumb" wire:key="tp-{{ $task->id }}-{{ $photo->id }}">
                        <img src="{{ \Illuminate\Support\Facades\Storage::disk('public')->url($photo->path) }}" alt="">
                    </div>
                @endforeach
            </div>
        @else
            <div class="wp-pending-review" data-pending-label="{{ __('portal.pending_review') }}">
                <div class="wp-photo-grid">
                    @foreach ($issue->photos as $photo)
                        <div class="wp-photo-thumb" wire:key="tp-{{ $task->id }}-{{ $photo->id }}"><span></span></div>
                    @endforeach
                </div>
            </div>
        @endif
    @endif

    @if ($completingTaskId === $task->id)
        <form wire:submit="submitCompleteTask"
              x-data
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
                @include('partials.wp-issue-photo-upload', ['model' => 'completingPhotos'])
                @error('completingPhotos.*') <p class="wp-error">{{ $message }}</p> @enderror
            </div>
            <div class="wp-stack-tight">
                <button type="submit" class="btn btn--primary btn--block">{{ __('portal.worker.confirm_complete') }}</button>
                <button type="button" class="btn btn--ghost btn--block btn--sm" wire:click="cancelCompleteTask">{{ __('common.button.cancel') }}</button>
            </div>
        </form>
    @else
        <div class="wp-stack-tight">
            @if ($task->canStart())
                <button type="button" class="btn btn--warning btn--block" wire:click="startTask({{ $task->id }})">
                    {{ __('portal.worker.start_task') }}
                </button>
            @endif
            @if ($task->canComplete())
                <button type="button" class="btn btn--primary btn--block" wire:click="beginCompleteTask({{ $task->id }})">
                    {{ __('portal.worker.complete_task') }}
                </button>
            @endif
        </div>
    @endif
</div>
