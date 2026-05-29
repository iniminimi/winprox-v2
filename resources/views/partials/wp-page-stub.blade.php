{{--
  Gedeelde stub voor nog-niet-uitgewerkte beheerspagina's: kop + "binnenkort".
  Props (via @include): $title, $text, $icon (optioneel), $subtitle (optioneel).
--}}
<div class="wp-stack">
    <div class="wp-stack-tight">
        <h1 class="wp-page-title">{{ $title }}</h1>
        @isset($subtitle)
            <p class="wp-muted">{{ $subtitle }}</p>
        @endisset
    </div>

    <div class="wp-card wp-card-pad wp-stub">
        <span class="wp-stub-icon">
            <x-wp-icon :name="$icon ?? 'document'" />
        </span>
        <p class="wp-stub-text">{{ $text }}</p>
    </div>
</div>
