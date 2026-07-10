@props([
    'locale' => null,
])

@php
    $dateLang = \App\Support\Translation\LocaleSupport::dateInputLang($locale);
@endphp

<input
    type="date"
    lang="{{ $dateLang }}"
    {{ $attributes->class(['wp-input', 'wp-date-input']) }}
/>
