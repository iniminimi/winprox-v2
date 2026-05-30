<div class="wp-stack">
    <div class="wp-page-head">
        <div class="wp-stack-tight">
            <h1 class="wp-page-title">{{ __('issues.list.title') }}</h1>
            <p class="wp-muted">{{ __('issues.list.subtitle') }}</p>
        </div>
        <div class="wp-cluster">
            <a href="{{ route('briefing.print') }}" target="_blank" rel="noopener noreferrer" class="btn btn--ghost btn--sm">{{ __('issues.briefing') }}</a>
            <button type="button" class="btn btn--primary btn--sm" wire:click="openCreateModal">
                <x-wp-icon name="plus" class="wp-icon" />
                <span>{{ __('issues.list.add') }}</span>
            </button>
        </div>
    </div>

    @if (session('success'))
        <div class="wp-flash wp-flash--success">{{ session('success') }}</div>
    @endif

    <div class="wp-card wp-card-pad wp-stack-tight">
        <div class="wp-filter-bar">
            <div class="wp-field wp-filter-field">
                <label class="wp-label" for="statusFilter">{{ __('issues.filter.status') }}</label>
                <select id="statusFilter" class="wp-select" wire:model.defer="statusFilter">
                    <option value="">{{ __('issues.filter.status_all') }}</option>
                    @foreach ($statuses as $status)
                        <option value="{{ $status->value }}">{{ __($status->labelKey()) }}</option>
                    @endforeach
                </select>
            </div>

            <div class="wp-field wp-filter-field">
                <label class="wp-label" for="teamFilter">{{ __('issues.filter.team') }}</label>
                <select id="teamFilter" class="wp-select" wire:model.defer="teamFilter">
                    <option value="">{{ __('issues.filter.team_all') }}</option>
                    @foreach ($teams as $team)
                        <option value="{{ $team->id }}">{{ $team->name }}</option>
                    @endforeach
                </select>
            </div>

            <div class="wp-field wp-filter-field wp-grow">
                <label class="wp-label" for="search">{{ __('issues.filter.search') }}</label>
                <input type="search" id="search" class="wp-input" wire:model.defer="search"
                       placeholder="{{ __('issues.filter.search_placeholder') }}">
            </div>
        </div>

        <div class="wp-row">
            <label class="wp-check">
                <input type="checkbox" wire:model.defer="recurring">
                {{ __('issues.filter.recurring') }}
            </label>
            <div class="wp-cluster">
                <button type="button" class="btn btn--primary btn--sm" wire:click="applyFilters">{{ __('issues.filter.apply') }}</button>
                @if ($hasFilters)
                    <button type="button" class="btn btn--ghost btn--sm" wire:click="resetFilters">{{ __('issues.filter.reset') }}</button>
                @endif
            </div>
        </div>
        <p class="wp-hint">{{ __('issues.filter.hint') }}</p>
    </div>

    @forelse ($groups as $group)
        <section class="wp-stack-tight" wire:key="group-{{ $group['status']->value }}">
            <div class="wp-group-head wp-group-head--{{ $group['status']->pillModifier() }}">
                <h2 class="wp-group-title">{{ __($group['status']->labelKey()) }}</h2>
                <span class="wp-group-count">{{ $group['issues']->count() }}</span>
            </div>

            <div class="wp-issue-grid">
                @foreach ($group['issues'] as $issue)
                    @php
                        $teamNames = $issue->tasks->map(fn ($t) => $t->team?->name)->filter()->unique()->values();
                        $locationLine = collect([
                            $issue->location?->name,
                            $issue->unit?->name,
                            $issue->location?->formattedAddress(),
                        ])->filter()->join(' · ');
                        $isHighlighted = $highlightIssue && (int) $highlightIssue === (int) $issue->id;
                    @endphp
                    <a href="{{ route('issues.show', $issue) }}"
                       class="wp-card wp-card-pad wp-melding-card {{ $isHighlighted ? 'wp-melding-card--highlight' : '' }}"
                       wire:key="issue-{{ $issue->id }}">
                        <div class="wp-row">
                            <span class="wp-melding-nr">{{ __('issues.card.nr', ['nr' => $issue->id]) }}</span>
                            <div class="wp-cluster">
                                @unless ($issue->isApproved())
                                    <span class="wp-pill wp-pill--progress">{{ __('issues.pending_review') }}</span>
                                @endunless
                                <span class="wp-pill wp-pill--{{ $issue->status->pillModifier() }}">{{ __($issue->status->labelKey()) }}</span>
                            </div>
                        </div>

                        <p class="wp-melding-desc">{{ $issue->description }}</p>

                        @if ($locationLine !== '')
                            <p class="wp-muted wp-melding-location">{{ $locationLine }}</p>
                        @endif

                        <div class="wp-melding-meta">
                            <span class="wp-muted">
                                {{ __($issue->source?->labelKey() ?? 'issues.card.source_manual') }}
                            </span>
                            <span class="wp-muted">
                                <x-wp-icon name="team" class="wp-icon" />
                                {{ $teamNames->isNotEmpty() ? $teamNames->join(', ') : __('issues.card.no_team') }}
                            </span>
                            <span class="wp-muted">
                                {{ __('issues.card.reported', [
                                    'name' => $issue->reporter_name ?: __('issues.card.unknown_reporter'),
                                    'datetime' => optional($issue->created_at)->timezone(config('app.timezone'))->format('d/m/Y H:i'),
                                ]) }}
                            </span>
                        </div>
                    </a>
                @endforeach
            </div>
        </section>
    @empty
        <div class="wp-card wp-card-pad">
            <p class="wp-muted">{{ $hasFilters ? __('issues.list.empty_filtered') : __('issues.list.empty') }}</p>
        </div>
    @endforelse

    @if ($showCreateModal)
        @include('livewire.issues.partials.create-modal')
    @endif
</div>
