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

@if ($hours->days->isEmpty())
    <div class="wp-card wp-card-pad">
        <p class="wp-muted">{{ __('time.portal.hours.empty') }}</p>
    </div>
@else
    <div class="wp-list wp-portal-hours-list">
        @foreach ($hours->days as $day)
            <div class="wp-card wp-portal-hours-day wp-stack-tight" wire:key="hours-day-{{ $day->dateKey }}">
                <div class="wp-cluster">
                    <strong>{{ $day->dateLabel }}</strong>
                    <span class="wp-pill wp-pill--{{ $day->isOpen ? 'progress' : 'done' }}">{{ __('time.status.'.($day->isOpen ? 'open' : 'closed')) }}</span>
                </div>
                <p>{{ $day->timesLine }} @include('partials.wp-portal-clock-alert', ['alert' => $day->clockAlert])</p>
                <p>{{ $day->breakLine }}</p>
            </div>
        @endforeach
    </div>
@endif
