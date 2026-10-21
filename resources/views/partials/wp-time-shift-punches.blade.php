@php
    $inPlace = $shift->clockInClockPoint?->attendancePlaceLabel() ?? __('time.shifts.clock_point_fallback');
    $outPlace = $shift->clockOutClockPoint?->attendancePlaceLabel() ?? $inPlace;
@endphp
<ul class="wp-time-shift-card__punch-list">
    <li class="wp-time-shift-card__punch" wire:key="shift-{{ $shift->id }}-punch-in">
        <span class="wp-time-shift-card__punch-mark wp-time-shift-card__punch-mark--in" aria-hidden="true">
            <x-wp-icon name="arrow-up-right" />
        </span>
        <div class="wp-time-shift-card__punch-copy">
            <p class="wp-time-shift-card__punch-line">{{ __('time.shifts.punch_in', ['time' => $shift->clock_in_at->format('H:i')]) }}</p>
            <p class="wp-muted wp-text-sm wp-time-shift-card__punch-place">{{ $inPlace }}</p>
        </div>
    </li>
    @if ($shift->clock_out_at)
        <li class="wp-time-shift-card__punch" wire:key="shift-{{ $shift->id }}-punch-out">
            <span class="wp-time-shift-card__punch-mark wp-time-shift-card__punch-mark--out" aria-hidden="true">
                <x-wp-icon name="arrow-down-left" />
            </span>
            <div class="wp-time-shift-card__punch-copy">
                <p class="wp-time-shift-card__punch-line">{{ __('time.shifts.punch_out', ['time' => $shift->clock_out_at->format('H:i')]) }}</p>
                <p class="wp-muted wp-text-sm wp-time-shift-card__punch-place">{{ $outPlace }}</p>
            </div>
        </li>
    @endif
    @foreach ($shift->breaks as $break)
        <li class="wp-time-shift-card__punch wp-time-shift-card__punch--break" wire:key="shift-{{ $shift->id }}-break-{{ $break->id }}">
            <p class="wp-muted wp-text-sm">
                @if ($break->ended_at)
                    {{ __('time.shifts.break_range', ['start' => $break->started_at->format('H:i'), 'end' => $break->ended_at->format('H:i')]) }}
                @else
                    {{ __('time.shifts.break_open', ['start' => $break->started_at->format('H:i')]) }}
                @endif
            </p>
        </li>
    @endforeach
</ul>
