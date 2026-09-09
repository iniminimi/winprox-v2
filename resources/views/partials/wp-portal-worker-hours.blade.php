<nav class="wp-pagination" aria-label="{{ __('time.portal.hours.month_nav') }}">
    <div class="wp-pagination__pages">
        <button type="button" class="wp-pagination__control" wire:click="previousHoursMonth" aria-label="{{ __('time.portal.hours.previous_month') }}">‹</button>
        <span class="wp-pagination__page is-active">{{ $hoursMonthLabel }}</span>
        @if ($hoursIsCurrentMonth)
            <span class="wp-pagination__control" aria-disabled="true" aria-label="{{ __('time.portal.hours.next_month') }}">›</span>
        @else
            <button type="button" class="wp-pagination__control" wire:click="nextHoursMonth" aria-label="{{ __('time.portal.hours.next_month') }}">›</button>
        @endif
    </div>
</nav>

<p class="wp-muted">{{ __('time.portal.hours.total', ['duration' => \App\Support\Time\WorkDurationFormatter::format($hours->totalNetMinutes)]) }}</p>

@if ($hours->shifts->isEmpty())
    <div class="wp-card wp-card-pad">
        <p class="wp-muted">{{ __('time.portal.hours.empty') }}</p>
    </div>
@else
    <div class="wp-list">
        @foreach ($hours->shifts as $shift)
            <div class="wp-card wp-card-pad wp-stack" wire:key="hours-shift-{{ $shift->id }}">
                <div class="wp-cluster">
                    <strong class="wp-text-body">{{ $shift->clock_in_at->format('d-m-Y') }}</strong>
                    <span class="wp-pill wp-pill--{{ $shift->status->isOpen() ? 'progress' : 'done' }}">{{ __('time.status.'.$shift->status->value) }}</span>
                    @if ($shift->isManuallyClockedIn())
                        <span class="wp-pill wp-pill--done">{{ __('time.manual_clock_in.badge') }}</span>
                    @endif
                </div>
                <p class="wp-muted">
                    {{ $shift->clock_in_at->format('H:i') }}
                    @if ($shift->clock_out_at)
                        – {{ $shift->clock_out_at->format('H:i') }}
                    @endif
                </p>
                <p class="wp-muted">
                    {{ __('time.shifts.break_minutes', ['duration' => \App\Support\Time\WorkDurationFormatter::format($shift->total_break_minutes)]) }}
                    &middot; {{ __('time.shifts.worked', ['duration' => \App\Support\Time\WorkDurationFormatter::format($shift->netWorkMinutes())]) }}
                </p>
                <p class="wp-muted">
                    {{ __('time.shifts.clocked_in_at', ['name' => $shift->clockInClockPoint?->name ?? '—']) }}
                    @if ($shift->clockOutClockPoint)
                        &middot; {{ __('time.shifts.clocked_out_at', ['name' => $shift->clockOutClockPoint->name]) }}
                    @endif
                </p>
            </div>
        @endforeach
    </div>
@endif
