<div class="wp-stack wp-tasks-page">
    @if ($onboarding->showTeamsBanner())
        <x-wp-onboarding-banner stage="teams" />
    @elseif ($onboarding->showCategoriesOrLocationsBanner())
        <x-wp-onboarding-banner stage="categories" />
    @else
        <div class="wp-page-head">
            <div class="wp-grow wp-stack-tight">
                <x-wp-page-head-title
                    icon="tasks"
                    :title="__('tasks.list.title')"
                    help-page="tasks.list"
                    :subtitle="__('tasks.list.subtitle')"
                />
            </div>
            <div class="wp-cluster wp-page-actions">
                <a href="{{ route('briefing.print') }}" target="_blank" rel="noopener noreferrer" class="btn btn--ghost btn--sm">{{ __('tasks.briefing') }}</a>
            </div>
        </div>

        @if (!$hasNoIssues)
            <div class="wp-card wp-filter-panel">
                <div class="wp-filter-grid">
                    <div class="wp-filter-field">
                        <label class="wp-label" for="statusFilter">{{ __('tasks.filter.status') }}</label>
                        <div class="wp-filter-status-row">
                            <select id="statusFilter" class="wp-select" wire:model.defer="statusFilter">
                                <option value="">{{ __('tasks.filter.status_all') }}</option>
                                @foreach ($statuses as $status)
                                    <option value="{{ $status->value }}">{{ __($status->labelKey()) }}</option>
                                @endforeach
                            </select>
                            <button type="button" class="btn btn--primary btn--sm wp-filter-go-btn" wire:click="applyFilters">{{ __('tasks.filter.apply') }}</button>
                        </div>
                    </div>

                    <div class="wp-filter-field">
                        <label class="wp-label" for="teamFilter">{{ __('tasks.filter.team') }}</label>
                        <select id="teamFilter" class="wp-select" wire:model.defer="teamFilter">
                            <option value="">{{ __('tasks.filter.team_all') }}</option>
                            @foreach ($teams as $team)
                                <option value="{{ $team->id }}">{{ $team->name }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div class="wp-filter-field">
                        <label class="wp-label" for="priorityFilter">{{ __('tasks.filter.priority') }}</label>
                        <select id="priorityFilter" class="wp-select" wire:model.defer="priorityFilter">
                            <option value="">{{ __('tasks.filter.priority_all') }}</option>
                            @foreach ($priorities as $priority)
                                <option value="{{ $priority->value }}">{{ $priority->label() }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div class="wp-filter-field">
                        <label class="wp-label" for="search">{{ __('tasks.filter.search') }}</label>
                        <input type="search" id="search" class="wp-input" wire:model.defer="search"
                               placeholder="{{ __('tasks.filter.search_placeholder') }}">
                    </div>

                    <div class="wp-filter-field wp-filter-field--recurring">
                        <span class="wp-label" id="recurringFilterLabel">{{ __('tasks.filter.recurring') }}</span>
                        <div class="wp-filter-recurring-row">
                            <label class="wp-check" aria-labelledby="recurringFilterLabel">
                                <input type="checkbox" wire:model.defer="recurring">
                                {{ __('tasks.filter.recurring_only') }}
                            </label>
                            @if ($hasFilters)
                                <button type="button" class="btn btn--ghost btn--sm" wire:click="resetFilters">{{ __('tasks.filter.reset') }}</button>
                            @endif
                        </div>
                    </div>
                </div>
                <p class="wp-hint wp-filter-panel-hint">{{ __('tasks.filter.hint') }}</p>
            </div>
        @endif

        @forelse ($groups as $group)
            <section class="wp-status-block" wire:key="task-group-{{ $group['status']->value }}">
                <div class="wp-group-head wp-group-head--{{ $group['status']->pillModifier() }}">
                    <h2 class="wp-group-title">{{ __($group['status']->labelKey()) }}</h2>
                    <span class="wp-group-count">{{ trans_choice('tasks.list.group_count', $group['tasks']->count()) }}</span>
                </div>

                <div class="wp-status-block__list">
                    @foreach ($group['tasks'] as $task)
                        @php
                            $issue = $task->issue;
                            $cardTitle = collect([
                                $issue?->location?->name,
                                $issue?->unit?->name,
                                __('tasks.card.kind_nr', ['nr' => $task->id]),
                            ])->filter()->join(', ');
                            $addressLine = $issue?->location
                                ? trim(($issue->location->country_code ?: 'BE').' '.$issue->location->formattedAddress())
                                : '';
                            $teamName = $task->team?->name ?: __('tasks.card.no_team');
                            $taskLine = __('tasks.card.line_team', ['team' => $teamName]);
                            $metaParts = collect();
                            if ($issue) {
                                $metaParts->push(__('tasks.card.meta_context', ['issue_nr' => $issue->id]));
                            }
                            if ($task->scheduled_for || $task->due_at) {
                                $metaParts->push(__('tasks.card.scheduled', [
                                    'date' => ($task->scheduled_for ?? $task->due_at)?->format('d/m/Y'),
                                ]));
                            }
                            $description = trim((string) ($task->note ?: $issue?->description));
                        @endphp
                        <a href="{{ route('tasks.show', $task) }}"
                           class="wp-issue-row"
                           wire:key="task-{{ $task->id }}">
                            <div class="wp-grow wp-stack-tight">
                                @if ($cardTitle !== '')
                                    <p class="wp-issue-card-title">{{ $cardTitle }}</p>
                                @endif
                                @if ($addressLine !== '')
                                    <p class="wp-issue-card-meta">{{ $addressLine }}</p>
                                @endif
                                <p class="wp-issue-card-title">{{ $taskLine }}</p>
                                @if ($metaParts->isNotEmpty())
                                    <p class="wp-issue-card-meta">{{ $metaParts->join(' · ') }}</p>
                                @endif
                                @if ($description !== '')
                                    <p class="wp-issue-card-desc">{{ $description }}</p>
                                @endif
                            </div>
                            <div class="wp-issue-row-meta">
                                @if ($task->is_recurring_cycle)
                                    <span class="wp-pill wp-pill--done">{{ __('tasks.card.recurring') }}</span>
                                @endif
                                <span class="wp-badge {{ $task->priority->badgeClass() }}">
                                    <x-wp-icon :name="$task->priority->icon()" class="wp-icon wp-icon--sm" />
                                    {{ $task->priority->label() }}
                                </span>
                                <span class="wp-pill wp-pill--{{ $task->status->pillModifier() }}">{{ __($task->status->labelKey()) }}</span>
                            </div>
                        </a>
                    @endforeach
                </div>
            </section>
        @empty
            <div class="wp-card wp-card-pad">
                @if ($hasNoIssues)
                    <p class="wp-muted">
                        {{ __('tasks.list.empty_no_issues') }}
                        <a href="{{ route('issues.index') }}" class="btn btn--primary btn--sm">{{ __('tasks.list.manage_issues') }}</a>
                    </p>
                @else
                    <p class="wp-muted">{{ $hasFilters ? __('tasks.list.empty_filtered') : __('tasks.list.empty') }}</p>
                @endif
            </div>
        @endforelse
    @endif
</div>
