@php
    $visitPlace = $shift->openVisit
        ? trim(($shift->openVisit->location?->name ?? '').' · '.($shift->openVisit->unit?->localizedName() ?? ''), ' · ')
        : '';
@endphp
@if ($visitPlace !== '')
    <span class="wp-pill wp-pill--done">{{ __('time.presence.working_at', ['place' => $visitPlace]) }}</span>
@endif
