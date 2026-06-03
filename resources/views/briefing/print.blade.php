@php
    $selectedTeam = $briefing->team;
    $teamOptions = $briefing->teams;
    $openTasksOnly = $briefing->openTasksOnly;
    $briefingReady = $briefing->isReady();
    $briefingBaseParams = array_filter(['team' => $selectedTeam?->id]);
    $briefingDayParams = array_merge($briefingBaseParams, ['date' => $briefing->date->toDateString()]);
    $briefingOpenTasksParams = array_merge($briefingBaseParams, ['open_tasks' => 1]);
    $organisationLogoUrl = $tenant->logoPublicUrl();
    $organisationAddress = $tenant->organisationAddressLine();
@endphp

<x-layouts.print :title="__('briefing.document_title')">
    <div class="wp-container wp-stack">
        <div class="wp-page-head">
            <div class="wp-grow wp-stack-tight">
                <x-wp-page-head-title
                    icon="calendar"
                    :title="__('briefing.screen_brand')"
                />
                @if ($briefingReady)
                    <div class="wp-cluster wp-no-print">
                        @if ($openTasksOnly)
                            <a href="{{ route('briefing.print', $briefingDayParams) }}" class="btn btn--ghost btn--sm">{{ __('briefing.view_by_day') }}</a>
                        @else
                            <a href="{{ route('briefing.print', $briefingOpenTasksParams) }}" class="btn btn--ghost btn--sm">{{ __('briefing.open_tasks_button') }}</a>
                        @endif
                        <button type="button" class="btn btn--primary btn--sm" onclick="window.print()">{{ __('briefing.print') }}</button>
                    </div>
                @endif
            </div>
            <div class="wp-cluster wp-cluster--tight wp-page-actions">
                @if ($organisationLogoUrl)
                    <img
                        src="{{ $organisationLogoUrl }}"
                        alt=""
                        class="wp-org-logo-preview"
                        width="120"
                        height="120"
                    >
                @endif
                <p class="wp-muted">
                    <strong class="wp-text-body">{{ $tenant->name }}</strong>
                    @if ($tenant->email)
                        <br>{{ $tenant->email }}
                    @endif
                    @if ($tenant->phone)
                        <br>{{ $tenant->phone }}
                    @endif
                    @if ($organisationAddress)
                        <br>{{ $organisationAddress }}
                    @endif
                </p>
            </div>
        </div>

        <form method="get" action="{{ route('briefing.print') }}" class="wp-card wp-filter-panel wp-no-print">
            @if ($openTasksOnly)
                <input type="hidden" name="open_tasks" value="1">
            @endif
            <div class="wp-filter-grid">
                @if ($teamOptions->count() > 1)
                    <div class="wp-filter-field">
                        <label class="wp-label" for="briefing-team-id">{{ __('briefing.select_team') }}</label>
                        <div class="wp-filter-status-row">
                            <select id="briefing-team-id" name="team" class="wp-select" required>
                                <option value="">{{ __('briefing.team_choose') }}</option>
                                @foreach ($teamOptions as $option)
                                    <option value="{{ $option->id }}" @selected((int) ($selectedTeam?->id ?? 0) === (int) $option->id)>
                                        {{ $option->name }}
                                    </option>
                                @endforeach
                            </select>
                            <button type="submit" class="btn btn--primary btn--sm wp-filter-go-btn">{{ __('briefing.apply_filters') }}</button>
                        </div>
                    </div>
                @elseif ($selectedTeam)
                    <input type="hidden" name="team" value="{{ $selectedTeam->id }}">
                @endif

                @unless ($openTasksOnly)
                    <div class="wp-filter-field">
                        <label class="wp-label" for="briefing-date">{{ __('briefing.select_date') }}</label>
                        <input id="briefing-date" type="date" name="date" class="wp-input" value="{{ $briefing->date->toDateString() }}" required>
                    </div>
                @endunless

                @if ($teamOptions->count() <= 1)
                    <div class="wp-filter-field">
                        <span class="wp-label" aria-hidden="true">&nbsp;</span>
                        <button type="submit" class="btn btn--primary btn--sm wp-filter-go-btn">{{ __('briefing.apply_filters') }}</button>
                    </div>
                @endif
            </div>
        </form>

        @if ($briefingReady)
            <div class="wp-card wp-card-pad wp-stack wp-no-print-break">
                <div class="wp-stack-tight">
                    <h2 class="wp-page-title">{{ __('briefing.title') }}</h2>
                    <p class="wp-muted">
                        {{ $selectedTeam->name }}
                        &middot;
                        @if ($openTasksOnly)
                            {{ __('briefing.scope_open_tasks') }}
                        @else
                            {{ $briefing->date->format('d/m/Y') }}
                        @endif
                        @if ($briefing->lineCount > 0)
                            &middot; {{ __('briefing.lines_count', ['count' => $briefing->lineCount]) }}
                        @endif
                    </p>
                </div>

                @if ($briefing->unitLines->isEmpty() && $briefing->generalLines->isEmpty())
                    <p class="wp-muted">{{ __($openTasksOnly ? 'briefing.empty_team_open' : 'briefing.empty_team') }}</p>
                @else
                    @if ($briefing->unitLines->isNotEmpty())
                        <section class="wp-stack-tight">
                            <h3 class="wp-section-title">{{ __('briefing.section_units') }}</h3>
                            <ul class="wp-briefing-list">
                                @foreach ($briefing->unitLines as $line)
                                    <li>
                                        <strong>{{ $line->locationLabel }}</strong>
                                        <span class="wp-muted"> &rarr; {{ $line->summary }}@if ($line->locationHint) &middot; {{ $line->locationHint }}@endif</span>
                                    </li>
                                @endforeach
                            </ul>
                        </section>
                    @endif

                    @if ($briefing->generalLines->isNotEmpty())
                        <section class="wp-stack-tight">
                            <h3 class="wp-section-title">{{ __('briefing.section_general') }}</h3>
                            <ul class="wp-briefing-list">
                                @foreach ($briefing->generalLines as $line)
                                    <li>
                                        <strong>{{ $line->locationLabel }}</strong>
                                        <span class="wp-muted"> &rarr; {{ $line->summary }}@if ($line->locationHint) &middot; {{ $line->locationHint }}@endif</span>
                                    </li>
                                @endforeach
                            </ul>
                        </section>
                    @endif
                @endif
            </div>
        @elseif ($teamOptions->count() > 1)
            <p class="wp-muted wp-no-print">{{ __('briefing.select_team') }}</p>
        @endif

        <p class="wp-muted">{{ __('briefing.powered_by') }}</p>
    </div>
</x-layouts.print>
