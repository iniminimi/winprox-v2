@props([
    'level' => 1,
])

@php
    $level = max(1, min(5, (int) $level));
@endphp

<img
    src="{{ asset('images/battery'.$level.'.png') }}"
    alt=""
    {{ $attributes }}
>
