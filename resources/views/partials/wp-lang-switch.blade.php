{{--
  App-taalkeuze (beheer, welcome, auth) via /locale/{locale}.
  Props: $variant ('sidebar' default | 'inline').
--}}
@props(['variant' => 'sidebar'])

<div class="wp-lang wp-lang--{{ $variant }}">
    @include('partials.wp-lang-select', ['variant' => $variant, 'driver' => 'route'])
</div>
