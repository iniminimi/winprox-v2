<div class="wp-card wp-card-pad wp-stack">
    <div class="wp-row">
        <h2 class="wp-section-title">{{ __('issues.show.updates') }}</h2>
        @can('update', $issue)
            <button type="button" class="btn btn--ghost btn--sm" wire:click="openUpdateModal">
                <x-wp-icon name="plus" class="wp-icon" />
                <span>{{ __('issues.show.add_update_button') }}</span>
            </button>
        @endcan
    </div>

    @forelse ($issue->updates as $update)
        <div class="wp-card wp-card-pad wp-stack-tight wp-surface-muted" wire:key="update-{{ $update->id }}">
            <div class="wp-row">
                <span aria-hidden="true"></span>
                <p class="wp-muted wp-text-sm">{{ $update->created_at?->format('d/m/Y H:i') }}</p>
            </div>

            @if ($update->worker)
                <p class="wp-muted wp-text-sm">
                    {{ __('issues.show.added_by_worker') }} {{ $update->worker->displayName() }}
                </p>
            @elseif ($update->user)
                <p class="wp-muted wp-text-sm">
                    {{ __('issues.show.added_by') }} {{ $update->user->name }}
                </p>
            @endif

            @if (filled($update->body))
                <p class="wp-text-body">{{ $update->body }}</p>
            @elseif ($update->kind && $update->kind !== 'note')
                <p class="wp-text-body">{{ __('issues.updates.kind.'.$update->kind) }}</p>
            @endif

            @include('partials.wp-issue-photo-gallery', [
                'photos' => $update->photos,
                'wireKeyPrefix' => 'up-'.$update->id,
            ])
        </div>
    @empty
        <p class="wp-muted">{{ __('issues.show.updates_empty') }}</p>
    @endforelse
</div>
