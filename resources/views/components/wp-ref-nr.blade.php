@props([
    /** issue|task */
    'type' => 'issue',
    'id',
])

@php
    $labelKey = $type === 'task' ? 'tasks.card.nr' : 'issues.card.nr';
@endphp

<span {{ $attributes->merge(['class' => 'wp-melding-nr']) }}>{{ __($labelKey, ['nr' => $id]) }}</span>
