<div class="wp-stack wp-issues-page">
    @if ($onboarding->showTeamsBanner())
        <x-wp-onboarding-banner stage="teams" />
    @elseif ($onboarding->showCategoriesOrLocationsBanner())
        <x-wp-onboarding-banner stage="categories" />
    @else
        <div class="wp-page-head">
            <div class="wp-grow wp-stack-tight">
                <x-wp-page-head-title
                    icon="issues"
                    :title="__('issues.list.title')"
                    help-page="issues.list"
                    :subtitle="__('issues.list.subtitle')"
                />
            </div>
            <div class="wp-cluster wp-page-actions">
                <button type="button" class="btn btn--primary btn--sm @if($total === 0) wp-badge-critical @endif" wire:click="openCreateModal">
                    {{ __('issues.list.add') }}
                </button>
                <a href="{{ route('briefing.print') }}" target="_blank" rel="noopener noreferrer" class="btn btn--ghost btn--sm">{{ __('issues.briefing') }}</a>
            </div>
        </div>

        @if (session('success'))
            <div class="wp-flash wp-flash--success">{{ session('success') }}</div>
        @endif

        @if($total > 0)
            <div class="wp-card wp-filter-panel">
                <div class="wp-filter-grid">
                    <div class="wp-filter-field">
                        <label class="wp-label" for="statusFilter">{{ __('issues.filter.status') }}</label>
                        <div class="wp-filter-status-row">
                            <select id="statusFilter" class="wp-select" wire:model.defer="statusFilter">
                                <option value="">{{ __('issues.filter.status_all') }}</option>
                                @foreach ($statuses as $status)
                                    <option value="{{ $status->value }}">{{ __($status->labelKey()) }}</option>
                                @endforeach
                            </select>
                            <button type="button" class="btn btn--primary btn--sm wp-filter-go-btn" wire:click="applyFilters">{{ __('issues.filter.apply') }}</button>
                        </div>
                    </div>

                    <div class="wp-filter-field">
                        <label class="wp-label" for="teamFilter">{{ __('issues.filter.team') }}</label>
                        <select id="teamFilter" class="wp-select" wire:model.defer="teamFilter">
                            <option value="">{{ __('issues.filter.team_all') }}</option>
                            @foreach ($teams as $team)
                                <option value="{{ $team->id }}">{{ $team->name }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div class="wp-filter-field">
                        <label class="wp-label" for="search">{{ __('issues.filter.search') }}</label>
                        <input type="search" id="search" class="wp-input" wire:model.defer="search"
                               placeholder="{{ __('issues.filter.search_placeholder') }}">
                    </div>

                    <div class="wp-filter-field wp-filter-field--recurring">
                        <span class="wp-label" id="recurringFilterLabel">{{ __('issues.filter.recurring') }}</span>
                        <div class="wp-filter-recurring-row">
                            <label class="wp-check" aria-labelledby="recurringFilterLabel">
                                <input type="checkbox" wire:model.defer="recurring">
                                {{ __('issues.filter.recurring_only') }}
                            </label>
                            @if ($hasFilters)
                                <button type="button" class="btn btn--ghost btn--sm" wire:click="resetFilters">{{ __('issues.filter.reset') }}</button>
                            @endif
                        </div>
                    </div>
                </div>
                <p class="wp-hint wp-filter-panel-hint">{{ __('issues.filter.hint') }}</p>
            </div>
        @endif

        @forelse ($groups as $group)
            <section class="wp-status-block" wire:key="group-{{ $group['status']->value }}">
                <div class="wp-group-head wp-group-head--{{ $group['status']->pillModifier() }}">
                    <h2 class="wp-group-title">{{ __($group['status']->labelKey()) }}</h2>
                    <span class="wp-group-count">{{ trans_choice('issues.list.group_count', $group['issues']->count()) }}</span>
                </div>

                <div class="wp-status-block__list">
                    @foreach ($group['issues'] as $issue)
                        @include('partials.wp-issue-list-row', [
                            'issue' => $issue,
                            'highlight' => $highlightIssue && (int) $highlightIssue === (int) $issue->id,
                        ])
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
    @endif
</div>
