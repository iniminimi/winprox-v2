<div class="wp-stack">
    <div class="wp-page-head">
        <div class="wp-stack-tight">
            <h1 class="wp-page-title">{{ __('issues.list.title') }}</h1>
            <p class="wp-muted">{{ __('issues.list.subtitle') }}</p>
        </div>
        <div class="wp-cluster">
            <button type="button" class="btn btn--ghost btn--sm">{{ __('issues.briefing') }}</button>
            <a href="{{ route('issues.create') }}" class="btn btn--primary btn--sm">
                <x-wp-icon name="plus" class="wp-icon" />
                <span>{{ __('issues.list.add') }}</span>
            </a>
        </div>
    </div>

    <div class="wp-card wp-card-pad wp-stack-tight">
        <div class="wp-filter-bar">
            <div class="wp-field wp-filter-field">
                <label class="wp-label" for="statusFilter">{{ __('issues.filter.status') }}</label>
                <div class="wp-cluster wp-cluster--tight">
                    <select id="statusFilter" class="wp-select" wire:model="statusFilter">
                        <option value="">{{ __('issues.filter.status_all') }}</option>
                        @foreach ($statuses as $status)
                            <option value="{{ $status->value }}">{{ __($status->labelKey()) }}</option>
                        @endforeach
                    </select>
                    <button type="button" class="btn btn--primary" wire:click="applyFilters">{{ __('issues.filter.apply') }}</button>
                </div>
            </div>

            <div class="wp-field wp-filter-field">
                <label class="wp-label" for="teamFilter">{{ __('issues.filter.team') }}</label>
                <select id="teamFilter" class="wp-select" wire:model.live="teamFilter">
                    <option value="">{{ __('issues.filter.team_all') }}</option>
                    @foreach ($teams as $team)
                        <option value="{{ $team->id }}">{{ $team->name }}</option>
                    @endforeach
                </select>
            </div>

            <div class="wp-field wp-filter-field wp-grow">
                <label class="wp-label" for="search">{{ __('issues.filter.search') }}</label>
                <input type="search" id="search" class="wp-input" wire:model.live.debounce.400ms="search" placeholder="{{ __('issues.filter.search_placeholder') }}">
            </div>
        </div>

        <div class="wp-row">
            <label class="wp-check">
                <input type="checkbox" wire:model="recurring">
                {{ __('issues.filter.recurring') }}
            </label>
            @if ($hasFilters)
                <button type="button" class="btn btn--ghost btn--sm" wire:click="resetFilters">{{ __('issues.filter.reset') }}</button>
            @endif
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
                        $fromQr = filled($issue->reporter_name) || filled($issue->reporter_contact);
                    @endphp
                    <a href="{{ route('issues.show', $issue) }}" class="wp-card wp-card-pad wp-melding-card" wire:key="issue-{{ $issue->id }}">
                        <div class="wp-row">
                            <span class="wp-melding-nr">{{ __('issues.card.nr', ['nr' => $issue->id]) }}</span>
                            <span class="wp-pill wp-pill--{{ $issue->status->pillModifier() }}">{{ __($issue->status->labelKey()) }}</span>
                        </div>

                        <p class="wp-melding-desc">{{ \Illuminate\Support\Str::limit($issue->description, 120) }}</p>

                        <div class="wp-melding-meta">
                            <span class="wp-muted">
                                <x-wp-icon name="search" class="wp-icon" />
                                {{ $fromQr ? __('issues.card.source_qr') : __('issues.card.source_manual') }}
                            </span>
                            <span class="wp-muted">
                                <x-wp-icon name="team" class="wp-icon" />
                                {{ $teamNames->isNotEmpty() ? $teamNames->join(', ') : __('issues.card.no_team') }}
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
</div>
