<div class="wp-stack">
    <div class="wp-page-head">
        <div class="wp-stack-tight">
            <h1 class="wp-page-title">{{ __('tasks.list.title') }}</h1>
            <p class="wp-muted">{{ __('tasks.list.subtitle') }}</p>
        </div>
        <a href="{{ route('briefing.print') }}" target="_blank" rel="noopener noreferrer" class="btn btn--ghost btn--sm">{{ __('tasks.briefing') }}</a>
    </div>

    @if ($newTasks->isNotEmpty())
        <div class="wp-card wp-card-pad wp-stack-tight">
            <h2 class="wp-section-title">{{ __('tasks.list.new_block') }}</h2>
            <div class="wp-issue-grid">
                @foreach ($newTasks as $task)
                    <a href="{{ route('tasks.show', $task) }}" class="wp-card wp-card-pad wp-melding-card" wire:key="new-task-{{ $task->id }}">
                        <p class="wp-melding-desc">{{ $task->issue?->description }}</p>
                        <span class="wp-pill wp-pill--new">{{ __('common.status.new') }}</span>
                    </a>
                @endforeach
            </div>
        </div>
    @endif

    <div class="wp-card wp-card-pad wp-stack-tight">
        <div class="wp-filter-bar">
            <div class="wp-field wp-filter-field">
                <label class="wp-label" for="statusFilter">{{ __('tasks.filter.status') }}</label>
                <select id="statusFilter" class="wp-select" wire:model.defer="statusFilter">
                    <option value="">{{ __('tasks.filter.status_all') }}</option>
                    @foreach ($statuses as $status)
                        <option value="{{ $status->value }}">{{ __($status->labelKey()) }}</option>
                    @endforeach
                </select>
            </div>

            <div class="wp-field wp-filter-field">
                <label class="wp-label" for="teamFilter">{{ __('tasks.filter.team') }}</label>
                <select id="teamFilter" class="wp-select" wire:model.defer="teamFilter">
                    <option value="">{{ __('tasks.filter.team_all') }}</option>
                    @foreach ($teams as $team)
                        <option value="{{ $team->id }}">{{ $team->name }}</option>
                    @endforeach
                </select>
            </div>

            <div class="wp-field wp-filter-field wp-grow">
                <label class="wp-label" for="search">{{ __('tasks.filter.search') }}</label>
                <input type="search" id="search" class="wp-input" wire:model.defer="search"
                       placeholder="{{ __('tasks.filter.search_placeholder') }}">
            </div>
        </div>

        <div class="wp-row">
            <label class="wp-check">
                <input type="checkbox" wire:model.defer="recurring">
                {{ __('tasks.filter.recurring') }}
            </label>
            <div class="wp-cluster">
                <button type="button" class="btn btn--primary btn--sm" wire:click="applyFilters">{{ __('tasks.filter.apply') }}</button>
                @if ($hasFilters)
                    <button type="button" class="btn btn--ghost btn--sm" wire:click="resetFilters">{{ __('tasks.filter.reset') }}</button>
                @endif
            </div>
        </div>
        <p class="wp-hint">{{ __('tasks.filter.hint') }}</p>
    </div>

    @forelse ($groups as $group)
        <section class="wp-stack-tight" wire:key="task-group-{{ $group['status']->value }}">
            <div class="wp-group-head wp-group-head--{{ $group['status']->pillModifier() }}">
                <h2 class="wp-group-title">{{ __($group['status']->labelKey()) }}</h2>
                <span class="wp-group-count">{{ $group['tasks']->count() }}</span>
            </div>

            <div class="wp-issue-grid">
                @foreach ($group['tasks'] as $task)
                    @php
                        $issue = $task->issue;
                        $locationLine = collect([
                            $issue?->location?->name,
                            $issue?->unit?->name,
                            $issue?->location?->address,
                        ])->filter()->join(' · ');
                    @endphp
                    <a href="{{ route('tasks.show', $task) }}" class="wp-card wp-card-pad wp-melding-card" wire:key="task-{{ $task->id }}">
                        <div class="wp-row">
                            <span class="wp-pill wp-pill--{{ $task->status->pillModifier() }}">{{ __($task->status->labelKey()) }}</span>
                            @if ($task->scheduled_for || $task->due_at)
                                <span class="wp-muted">{{ __('tasks.card.scheduled', ['date' => ($task->scheduled_for ?? $task->due_at)?->format('d/m/Y')]) }}</span>
                            @endif
                        </div>
                        <p class="wp-melding-desc">{{ $issue?->description }}</p>
                        @if ($locationLine !== '')
                            <p class="wp-muted">{{ $locationLine }}</p>
                        @endif
                        <div class="wp-melding-meta">
                            <span class="wp-muted">
                                <x-wp-icon name="team" class="wp-icon" />
                                {{ $task->team?->name ?: __('tasks.card.no_team') }}
                            </span>
                        </div>
                    </a>
                @endforeach
            </div>
        </section>
    @empty
        <div class="wp-card wp-card-pad">
            <p class="wp-muted">{{ $hasFilters ? __('tasks.list.empty_filtered') : __('tasks.list.empty') }}</p>
        </div>
    @endforelse
</div>
