@php
    $clockedBreakMinutes = (int) $shift->breaks->sum(fn ($break) => $break->durationMinutes());
    $requiredBreakMinutes = (int) ($shift->team?->required_break_minutes ?? 0);
    $qualifiesForTeamBreak = $shift->clock_out_at !== null
        && app(\App\Actions\Time\ResolveWorkShiftBreakMinutesAction::class)
            ->qualifiesForRequiredBreak($shift, $shift->clock_out_at);
    $needsRequiredBreak = $qualifiesForTeamBreak
        && (int) $shift->total_break_minutes < $requiredBreakMinutes;
    $appliedTeamBreak = $qualifiesForTeamBreak
        && (int) $shift->total_break_minutes >= $requiredBreakMinutes
        && (int) $shift->total_break_minutes > $clockedBreakMinutes;
    $workedDuration = \App\Support\Time\WorkDurationFormatter::format($shift->netWorkMinutes());
    $breakDuration = \App\Support\Time\WorkDurationFormatter::format($shift->visibleBreakMinutes());
    $statusLabel = $shift->status === \App\Enums\WorkShiftStatus::ForceClosed && $shift->clock_out_source === \App\Enums\ClockSource::Auto
        ? __('time.status.auto_closed')
        : __('time.status.'.$shift->status->value);
    $plannedLabels = collect($planned ?? [])->map(function (\App\Models\PlannedShift $planned): string {
        $label = $planned->shiftType?->code ?: __('time.schedule.types.kinds.'.$planned->kind->value);
        $start = \App\Models\ShiftType::formatTime($planned->start_time);
        $end = \App\Models\ShiftType::formatTime($planned->end_time);

        return $start !== '' && $end !== '' ? $label.' '.$start.'–'.$end : $label;
    });
    $hasExtra = $shift->visits->isNotEmpty()
        || $shift->taskLogs->isNotEmpty()
        || $shift->hasLocationHops();
@endphp
<div class="wp-card wp-card-pad wp-time-shift-card" wire:key="shift-{{ $shift->id }}">
    <div class="wp-time-shift-card__col wp-time-shift-card__who-col">
        <p class="wp-time-shift-card__label">{{ __('time.filters.worker') }}</p>
        <div class="wp-time-shift-card__who">
            <x-wp-worker-avatar :worker="$shift->worker" size="md" />
            <div class="wp-time-shift-card__who-copy">
                <strong class="wp-time-shift-card__name">{{ $shift->worker?->displayName() }}</strong>
                @if ($shift->team)
                    <p class="wp-muted wp-text-sm">{{ $shift->team->localizedName() }}</p>
                @endif
            </div>
        </div>
    </div>

    <div class="wp-time-shift-card__col wp-time-shift-card__worked-col">
        <p class="wp-time-shift-card__label">{{ __('time.shifts.worked_label') }}</p>
        <p class="wp-time-shift-card__worked wp-tabular">{{ $workedDuration }}</p>
        <p class="wp-muted wp-text-sm">{{ __('time.shifts.worked_label') }}</p>
        <p class="wp-muted wp-text-sm">{{ __('time.shifts.break_minutes', ['duration' => $breakDuration]) }}</p>
        @if ($appliedTeamBreak)
            <p class="wp-muted wp-text-sm">{{ __('time.required_break.applied_badge', ['duration' => $breakDuration]) }}</p>
        @endif
    </div>

    <div class="wp-time-shift-card__col wp-time-shift-card__date-col">
        <p class="wp-time-shift-card__label">{{ __('time.export.columns.date') }}</p>
        <p class="wp-time-shift-card__date">{{ $shift->clock_in_at->format('d-m-Y') }}</p>
        @if ($plannedLabels->isNotEmpty())
            <p class="wp-muted wp-text-sm">
                {{ __('time.shifts.planned_label') }}: {{ $plannedLabels->implode(' · ') }}
            </p>
        @endif
    </div>

    <div class="wp-time-shift-card__col wp-time-shift-card__registrations">
        <p class="wp-time-shift-card__label">{{ __('time.shifts.punches_heading') }}</p>
        @include('partials.wp-time-shift-punches', ['shift' => $shift])
    </div>

    <div class="wp-time-shift-card__col wp-time-shift-card__actions">
        <p class="wp-time-shift-card__label">{{ __('time.export.columns.status') }}</p>
        <div class="wp-time-shift-card__status">
            @if ($shift->isManuallyClockedIn())
                <span class="wp-pill wp-pill--progress">{{ __('time.manual_clock_in.badge') }}</span>
            @endif
            <span class="wp-pill {{ $shift->status->isOpen() ? 'wp-pill--progress' : 'wp-pill--closed' }}">{{ $statusLabel }}</span>
            @if ($shift->status->isOpen())
                <button type="button" class="btn btn--ghost btn--sm" wire:click="openForceClose({{ $shift->id }})">
                    {{ __('time.presence.force_close') }}
                </button>
            @elseif (auth()->user()?->can('correct', $shift))
                @if ($needsRequiredBreak)
                    <button type="button" class="btn btn--ghost btn--sm" wire:click="applyRequiredBreak({{ $shift->id }})">
                        {{ __('time.required_break.apply', ['duration' => \App\Support\Time\WorkDurationFormatter::format($requiredBreakMinutes)]) }}
                    </button>
                @endif
                <button type="button" class="btn btn--ghost btn--sm" wire:click="openCorrection({{ $shift->id }})">
                    {{ __('time.corrections.button') }}
                </button>
            @endif
        </div>
    </div>

    @if ($hasExtra)
    <div class="wp-time-shift-card__extra">
        @if ($shift->hasLocationHops())
            <div>
                <p class="wp-subhead">{{ __('time.shifts.location_hops_heading') }}</p>
                <ul class="wp-muted wp-text-sm wp-time-shift-card__list">
                    @foreach ($shift->locationHops() as $hopIndex => $hop)
                        @php
                            $hopAt = isset($hop['at']) ? \Illuminate\Support\Carbon::parse($hop['at'])->format('H:i') : '—';
                            $hopFrom = $hop['from_clock_point_name'] ?? '—';
                            $hopTo = $hop['to_clock_point_name'] ?? '—';
                        @endphp
                        <li wire:key="shift-{{ $shift->id }}-hop-{{ $hopIndex }}">
                            {{ __('time.shifts.location_hop', ['time' => $hopAt, 'from' => $hopFrom, 'to' => $hopTo]) }}
                        </li>
                    @endforeach
                </ul>
                @if ($shift->presenceClockPoint && (int) $shift->presence_clock_point_id !== (int) $shift->clock_in_clock_point_id)
                    <p class="wp-muted wp-text-sm">
                        {{ __('time.shifts.presence_at', ['name' => $shift->presenceClockPoint->attendancePlaceLabel()]) }}
                    </p>
                @endif
            </div>
        @endif
        @if ($shift->visits->isNotEmpty())
            <div>
                <p class="wp-subhead">{{ __('time.shifts.visits_heading') }}</p>
                <ul class="wp-muted wp-text-sm wp-time-shift-card__list">
                    @foreach ($shift->visits as $visit)
                        @php
                            $visitPlace = trim(($visit->location?->name ?? '').' · '.($visit->unit?->localizedName() ?? ''), ' · ');
                            $visitStart = $visit->started_at?->format('H:i') ?? '—';
                        @endphp
                        <li wire:key="shift-{{ $shift->id }}-visit-{{ $visit->id }}">
                            @if ($visit->ended_at)
                                {{ __('time.shifts.visit_range', ['start' => $visitStart, 'end' => $visit->ended_at->format('H:i'), 'place' => $visitPlace]) }}
                            @else
                                {{ __('time.shifts.visit_open', ['start' => $visitStart, 'place' => $visitPlace]) }}
                            @endif
                        </li>
                    @endforeach
                </ul>
            </div>
        @endif
        @if ($shift->taskLogs->isNotEmpty())
            <div>
                <p class="wp-subhead">{{ __('time.shifts.tasks_heading') }}</p>
                <ul class="wp-muted wp-text-sm wp-time-shift-card__list">
                    @foreach ($shift->taskLogs as $log)
                        <li wire:key="shift-{{ $shift->id }}-task-{{ $log->id }}">
                            {{ $log->task?->displayDescription() ?: __('time.shifts.task_unknown') }}
                            @if ($log->ended_at)
                                &middot; {{ __('time.shifts.task_duration', ['minutes' => $log->durationMinutes()]) }}
                            @else
                                &middot; {{ __('time.shifts.task_open') }}
                            @endif
                        </li>
                    @endforeach
                </ul>
            </div>
        @endif
    </div>
    @endif
</div>
