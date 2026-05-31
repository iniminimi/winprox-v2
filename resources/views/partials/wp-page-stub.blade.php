{{--
  Gedeelde stub voor nog-niet-uitgewerkte beheerspagina's: kop + "binnenkort".
  Props (via @include): $title, $text, $icon (optioneel), $subtitle (optioneel).
--}}
<div class="wp-stack">
    <x-wp-page-head-title
        :icon="$icon ?? 'document'"
        :title="$title"
        :subtitle="$subtitle ?? null"
    />

    <div class="wp-card wp-card-pad wp-stub">
        <span class="wp-stub-icon">
            <x-wp-icon :name="$icon ?? 'document'" />
        </span>
        <p class="wp-stub-text">{{ $text }}</p>
    </div>
</div>
