{{--
  Stijlkeuze (beheer) — ingelogd; in zijbalk onder taal.
  $variant: sidebar
--}}
@php
    $variant = $variant ?? 'sidebar';
@endphp

<div class="wp-theme wp-theme--{{ $variant }}">
    @include('partials.wp-theme-select', ['variant' => $variant])
</div>
