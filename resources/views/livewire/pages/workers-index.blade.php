<div class="wp-stack" data-manual-capture="workers-index">
    <div class="wp-page-head">
        <div class="wp-grow wp-stack-tight">
            <x-wp-page-head-title
                icon="team"
                :title="__('team.workers_index.title')"
                help-page="team"
                :subtitle="__('team.workers_index.subtitle')"
            />
        </div>
    </div>

    <div class="wp-card wp-card-pad wp-stack">
        <div class="wp-filter-cell">
            <span class="wp-filter-inline-label">{{ __('team.workers_index.filters.label') }}</span>
            <input
                type="search"
                class="wp-input wp-input--compact"
                wire:model.live.debounce.300ms="search"
                placeholder="{{ __('team.workers_index.filters.search') }}"
                aria-label="{{ __('team.workers_index.filters.search') }}"
            >
            <select class="wp-select wp-select--compact" wire:model.live="teamFilter" aria-label="{{ __('team.workers_index.filters.team') }}">
                <option value="">{{ __('team.workers_index.filters.all_teams') }}</option>
                @foreach ($teams as $team)
                    <option value="{{ $team->id }}">{{ $team->name }}</option>
                @endforeach
            </select>
            <select class="wp-select wp-select--compact" wire:model.live="locationFilter" aria-label="{{ __('team.workers_index.filters.location') }}">
                <option value="">{{ __('team.workers_index.filters.all_locations') }}</option>
                @foreach ($locations as $location)
                    <option value="{{ $location->id }}">{{ $location->name ?: $location->address }}</option>
                @endforeach
            </select>
            <select class="wp-select wp-select--compact" wire:model.live="activeFilter" aria-label="{{ __('team.workers_index.filters.status') }}">
                <option value="all">{{ __('team.workers_index.filters.all_statuses') }}</option>
                <option value="active">{{ __('team.workers.active') }}</option>
                <option value="inactive">{{ __('team.workers.inactive') }}</option>
            </select>
            <span class="wp-pill wp-pill--closed">{{ __('team.workers_index.filters.count', ['count' => $workers->total()]) }}</span>
        </div>

        <div class="wp-list wp-list--entity-rows">
            @forelse ($workers as $worker)
                <div class="wp-issue-row" wire:key="worker-{{ $worker->id }}">
                    <div class="wp-grow wp-stack-tight">
                        <a
                            href="{{ route('team.index', ['section' => 'teams', 'worker' => $worker->id]) }}"
                            class="wp-issue-row-link wp-stack-tight"
                        >
                            <p class="wp-issue-card-title">{{ $worker->displayName() }}</p>
                            <p class="wp-issue-card-meta">
                                {{ __('team.workers_index.row.meta', [
                                    'team' => $worker->team?->name ?? '—',
                                    'locations' => $worker->locations->isNotEmpty()
                                        ? $worker->locations->pluck('name')->filter()->join(', ')
                                        : __('team.workers_index.row.all_locations'),
                                ]) }}
                            </p>
                        </a>
                    </div>

                    <div class="wp-cluster wp-cluster--tight">
                        @if ($worker->user_id)
                            <span class="wp-pill wp-pill--progress">{{ __('team.workers_index.linked_user') }}</span>
                        @endif
                        <span class="wp-pill wp-pill--{{ $worker->is_active ? 'done' : 'closed' }}">
                            {{ $worker->is_active ? __('team.workers.active') : __('team.workers.inactive') }}
                        </span>
                    </div>
                </div>
            @empty
                <p class="wp-muted">{{ __('team.workers_index.empty') }}</p>
            @endforelse
        </div>

        @if ($workers->hasPages())
            <div class="wp-pagination">
                {{ $workers->links() }}
            </div>
        @endif
    </div>
</div>
