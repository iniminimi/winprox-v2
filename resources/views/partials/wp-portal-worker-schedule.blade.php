<nav class="wp-pagination" aria-label="{{ __('time.portal.schedule.week_nav') }}">
    <div class="wp-pagination__pages">
        <button type="button" class="wp-pagination__control" wire:click="previousScheduleWeek" aria-label="{{ __('time.portal.schedule.previous_week') }}">‹</button>
        <span class="wp-pagination__page is-active">{{ $scheduleWeekLabel }}</span>
        <button type="button" class="wp-pagination__control" wire:click="nextScheduleWeek" aria-label="{{ __('time.portal.schedule.next_week') }}">›</button>
    </div>
</nav>

@if ($schedule->entries === [])
    <div class="wp-card wp-card-pad">
        <p class="wp-muted">{{ __('time.portal.schedule.empty') }}</p>
    </div>
@else
    <div class="wp-list">
        @foreach ($schedule->entries as $entry)
            <div class="wp-card wp-card-pad wp-stack" wire:key="schedule-entry-{{ $entry->date }}-{{ $entry->kind }}">
                <div class="wp-cluster">
                    <strong class="wp-text-body">{{ \Carbon\Carbon::parse($entry->date)->locale(app()->getLocale())->translatedFormat('l j M') }}</strong>
                    @if ($entry->kind !== 'work')
                        <span class="wp-pill">{{ __('time.schedule.types.kinds.'.$entry->kind) }}</span>
                    @endif
                </div>
                <p class="wp-text-body">{{ $entry->label }}</p>
            </div>
        @endforeach
    </div>
@endif
