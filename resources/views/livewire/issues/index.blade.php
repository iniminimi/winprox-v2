<div class="wp-stack wp-issues-page" data-manual-capture="issues-list">
    @if ($onboarding->showTeamsBanner())
        <x-wp-onboarding-banner stage="teams" />
    @elseif ($onboarding->showCategoriesOrLocationsBanner())
        <x-wp-onboarding-banner stage="categories" />
    @elseif ($onboarding->showClockPointBanner())
        <x-wp-onboarding-banner stage="clock_point" />
    @else
        <div class="wp-page-head">
            <div class="wp-grow wp-stack-tight">
                <x-wp-page-head-title
                    :assistant-video="asset('video/assistant_issue.mp4')"
                    assistant-video-loop
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
                <div class="wp-filter-form">
                    <p class="wp-filter-form__title">{{ __('common.list.filters_title') }}</p>

                    <div class="wp-filter-form__row">
                        <div class="wp-filter-cell">
                            <label class="wp-filter-inline-label" for="statusFilter">{{ __('issues.filter.status_label') }}</label>
                            <select id="statusFilter" class="wp-select" wire:model.defer="statusFilter">
                                <option value="">{{ __('issues.filter.status_all') }}</option>
                                @foreach ($statuses as $status)
                                    <option value="{{ $status->value }}">{{ __($status->labelKey()) }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="wp-filter-cell">
                            <label class="wp-filter-inline-label" for="teamFilter">{{ __('issues.filter.team_label') }}</label>
                            <select id="teamFilter" class="wp-select" wire:model.defer="teamFilter">
                                <option value="">{{ __('issues.filter.team_all') }}</option>
                                @foreach ($teams as $team)
                                    <option value="{{ $team->id }}">{{ $team->localizedName() }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>

                    <div class="wp-filter-form__row">
                        <div class="wp-filter-cell">
                            <label class="wp-filter-inline-label" for="perStatusLimit">{{ __('common.list.per_status_limit') }}</label>
                            <select id="perStatusLimit" class="wp-select" wire:model.live="perStatusLimit">
                                @foreach ($perStatusLimits as $limit)
                                    <option value="{{ $limit }}">{{ $limit }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>

                    <div class="wp-filter-form__row wp-filter-form__row--search">
                        <div class="wp-filter-cell wp-filter-cell--search">
                            <label class="wp-filter-inline-label" for="search">{{ __('issues.filter.search') }}</label>
                            <input type="search" id="search" class="wp-input" wire:model.defer="search"
                                   placeholder="{{ __('issues.filter.search_placeholder') }}">
                        </div>
                        <div class="wp-filter-cell wp-filter-cell--recurring">
                            <label class="wp-check">
                                <input type="checkbox" wire:model.defer="recurring">
                                {{ __('issues.filter.recurring_only') }}
                            </label>
                        </div>
                    </div>

                    <div class="wp-filter-form__actions">
                        <button type="button" class="btn btn--primary btn--sm" wire:click="applyFilters">{{ __('issues.filter.apply') }}</button>
                        @if ($hasFilters)
                            <button type="button" class="btn btn--ghost btn--sm" wire:click="resetFilters">{{ __('issues.filter.reset') }}</button>
                        @endif
                    </div>
                </div>
                <p class="wp-hint wp-filter-panel-hint">{{ __('issues.filter.hint') }}</p>
            </div>
        @endif

        @forelse ($groups as $group)
            <section class="wp-status-block" wire:key="group-{{ $group['kind'] }}-{{ $group['status']->value ?? 'pending' }}">
                <div class="wp-group-head wp-group-head--{{ $group['headModifier'] }}">
                    <h2 class="wp-group-title">{{ $group['title'] }}</h2>
                    <span class="wp-group-count">{{ $group['shown'] }}/{{ $group['total'] }}</span>
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
    @endif

    @if ($showCreateModal)
        @include('livewire.issues.partials.create-modal')
    @endif
</div>
