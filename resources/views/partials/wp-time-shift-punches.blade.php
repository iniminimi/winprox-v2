@php
    $inPlace = $shift->clockInClockPoint?->attendancePlaceLabel() ?? __('time.shifts.clock_point_fallback');
    $outPlace = $shift->clockOutClockPoint?->attendancePlaceLabel() ?? $inPlace;
@endphp
<p class="wp-subhead">{{ __('time.shifts.punches_heading') }}</p>
<ul class="wp-muted wp-text-sm wp-time-shift-card__list">
    <li wire:key="shift-{{ $shift->id }}-punch-in">{{ __('time.shifts.punch_in', ['time' => $shift->clock_in_at->format('H:i'), 'place' => $inPlace]) }}</li>
    @if ($shift->clock_out_at)
        <li wire:key="shift-{{ $shift->id }}-punch-out">{{ __('time.shifts.punch_out', ['time' => $shift->clock_out_at->format('H:i'), 'place' => $outPlace]) }}</li>
    @endif
    @foreach ($shift->breaks as $break)
        <li wire:key="shift-{{ $shift->id }}-break-{{ $break->id }}">
            @if ($break->ended_at)
                {{ __('time.shifts.break_range', ['start' => $break->started_at->format('H:i'), 'end' => $break->ended_at->format('H:i')]) }}
            @else
                {{ __('time.shifts.break_open', ['start' => $break->started_at->format('H:i')]) }}
            @endif
        </li>
    @endforeach
</ul>
