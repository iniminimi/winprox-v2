{{--
  Stijlkeuze (beheer) — ingelogd; op gsm naast taal bovenaan.
  $variant: sidebar | mobile
--}}
@php
    $variant = $variant ?? 'sidebar';
@endphp

<div class="wp-theme wp-theme--{{ $variant }}">
    @include('partials.wp-theme-select', ['variant' => $variant])
</div>
