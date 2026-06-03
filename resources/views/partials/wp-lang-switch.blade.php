{{--
  App-taalkeuze (beheer, welcome, auth) via /locale/{locale}.
  $variant: sidebar | inline | mobile
--}}
@php
    $variant = $variant ?? 'sidebar';
@endphp

<div class="wp-lang wp-lang--{{ $variant }}">
    @include('partials.wp-lang-select', ['variant' => $variant, 'driver' => 'route'])
</div>
