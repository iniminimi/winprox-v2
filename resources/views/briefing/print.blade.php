<x-layouts.print :title="__('dashboard.briefing_print')">
    <div class="wp-stack">
        <div class="wp-stack-tight">
            <h1 class="wp-page-title">{{ __('dashboard.briefing_print') }}</h1>
            <p class="wp-muted">
                {{ $date->format('d-m-Y') }} &middot; {{ $tenant?->name }}
                @if ($filterTeam)
                    &middot; {{ $filterTeam->name }}
                @endif
            </p>
        </div>

        @forelse ($grouped as $teamId => $teamTasks)
            @php
                $team = $teams->get($teamId);
                $teamName = $team?->name ?? __('dashboard.briefing_no_team');
            @endphp
            <div class="wp-card wp-card-pad wp-stack-tight wp-no-print-break">
                <h2 class="wp-section-title">{{ $teamName }}</h2>
                <ul class="wp-briefing-list">
                    @foreach ($teamTasks as $task)
                        <li wire:key="briefing-task-{{ $task->id }}">
                            <x-wp-ref-nr type="task" :id="$task->id" />
                            @if ($task->issue)
                                <span class="wp-muted">{{ __('tasks.card.issue_nr', ['nr' => $task->issue->id]) }}</span>
                            @endif
                            <strong>{{ \Illuminate\Support\Str::limit($task->issue?->description ?? '—', 120) }}</strong>
                            <span class="wp-muted">
                                @if ($task->issue?->location)
                                    &middot; {{ $task->issue->location->name }}
                                @endif
                                @if ($task->issue?->unit)
                                    &middot; {{ $task->issue->unit->name }}
                                @endif
                            </span>
                        </li>
                    @endforeach
                </ul>
            </div>
        @empty
            <p class="wp-muted">{{ __('dashboard.briefing_empty') }}</p>
        @endforelse

        <div class="wp-qr-actions wp-no-print">
            <button type="button" class="btn btn--primary" onclick="window.print()">{{ __('common.button.print') }}</button>
        </div>
    </div>
</x-layouts.print>
