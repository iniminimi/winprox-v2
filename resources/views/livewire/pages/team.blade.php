<div class="wp-stack">
    <div class="wp-stack-tight">
        <h1 class="wp-page-title">{{ __('pages.team.title') }}</h1>
        <p class="wp-muted">{{ __('pages.team.subtitle') }}</p>
    </div>

    @forelse ($teams as $team)
        <div class="wp-card wp-card-pad wp-stack-tight" wire:key="team-{{ $team->id }}">
            <div class="wp-row">
                <div class="wp-cluster">
                    <x-wp-icon name="team" class="wp-icon" />
                    <h2 class="wp-section-title">{{ $team->name }}</h2>
                </div>
                <span class="wp-pill wp-pill--closed">{{ __('pages.team.worker_count', ['count' => $team->workers->count()]) }}</span>
            </div>

            @if ($team->workers->isNotEmpty())
                <div class="wp-chip-row">
                    @foreach ($team->workers as $worker)
                        <span class="wp-chip" wire:key="worker-{{ $worker->id }}">{{ $worker->name }}</span>
                    @endforeach
                </div>
            @else
                <p class="wp-muted">{{ __('pages.team.no_workers') }}</p>
            @endif
        </div>
    @empty
        <div class="wp-card wp-card-pad wp-stub">
            <span class="wp-stub-icon"><x-wp-icon name="team" /></span>
            <p class="wp-stub-text">{{ __('pages.team.empty') }}</p>
        </div>
    @endforelse
</div>
